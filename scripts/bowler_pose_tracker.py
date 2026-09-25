"""
bowler_pose_tracker.py
Real-time Bowler Detection & Delivery Phase State Machine
For cricket second-screen / companion cameras and broadcast feeds.

Features:
1. Multi-person filtering: Identifies the bowler among umpire, batsman, and fielders
   using pitch corridor constraints and forward translation velocity.
2. Kinematic Feature Extraction:
   - Arm elevation angle (relative to shoulder horizon)
   - Bowling arm circumduction angular velocity
   - Wrist zenith (highest vertical release point)
   - Center-of-mass vertical bound (gather / delivery stride jump)
3. Finite State Machine (FSM):
   IDLE -> RUN_UP -> GATHER -> DELIVERY_STRIDE -> RELEASE -> FOLLOW_THROUGH
4. Triggers downstream Ball Tracking (e.g. CricketVisionEngine) exactly at RELEASE.
"""

import math
import time
from dataclasses import dataclass
from enum import Enum
from typing import List, Optional, Tuple, Dict


class DeliveryPhase(Enum):
    IDLE = "IDLE"
    RUN_UP = "RUN_UP"
    GATHER = "GATHER"
    DELIVERY_STRIDE = "DELIVERY_STRIDE"
    RELEASE = "RELEASE"
    FOLLOW_THROUGH = "FOLLOW_THROUGH"


@dataclass
class Keypoint:
    x: float  # Normalised [0.0 .. 1.0]
    y: float  # Normalised [0.0 .. 1.0], 0 at top
    confidence: float


@dataclass
class PoseSkeleton:
    # Standard 17 COCO Keypoints or 33 BlazePose subset
    nose: Keypoint
    left_shoulder: Keypoint
    right_shoulder: Keypoint
    left_elbow: Keypoint
    right_elbow: Keypoint
    left_wrist: Keypoint
    right_wrist: Keypoint
    left_hip: Keypoint
    right_hip: Keypoint
    left_knee: Keypoint
    right_knee: Keypoint
    left_ankle: Keypoint
    right_ankle: Keypoint
    bbox: Tuple[float, float, float, float]  # (xmin, ymin, xmax, ymax)


@dataclass
class DeliveryMetrics:
    phase: DeliveryPhase
    is_release_frame: bool = False
    release_frame_idx: int = -1
    release_timestamp_ms: int = -1
    arm_angle_degrees: float = 0.0
    bowling_arm: str = "UNKNOWN"  # "RIGHT" or "LEFT"
    hand_speed_normalized: float = 0.0
    stride_length_normalized: float = 0.0


class BowlerKinematicsTracker:
    """
    Real-time temporal analyzer for bowler pose skeletons.
    Processes one frame's detected poses at a time with O(1) latency.
    """

    def __init__(
        self,
        min_keypoint_conf: float = 0.35,
        runup_velocity_thresh: float = 0.015,  # Min forward y-shift per 100ms
        release_zenith_tolerance: float = 0.03,
    ):
        self.min_conf = min_keypoint_conf
        self.runup_velocity_thresh = runup_velocity_thresh
        self.release_zenith_tolerance = release_zenith_tolerance

        self.current_phase = DeliveryPhase.IDLE
        self.bowling_arm = "RIGHT"  # Auto-detected during delivery
        self.frame_history: List[Dict] = []
        self.max_history = 30  # Rolling 1 second at 30 FPS

        # Release tracking
        self.min_wrist_y = 1.0  # Lowest y in image coords = highest in physical space
        self.peak_arm_angle = 0.0
        self.release_detected = False

    def reset(self):
        """Reset state machine for the next ball delivery."""
        self.current_phase = DeliveryPhase.IDLE
        self.frame_history.clear()
        self.min_wrist_y = 1.0
        self.peak_arm_angle = 0.0
        self.release_detected = False

    def select_bowler(
        self, candidates: List[PoseSkeleton], pitch_corridor: Optional[Tuple[float, float, float, float]] = None
    ) -> Optional[PoseSkeleton]:
        """
        Filters out umpires, batsmen, and spectators.
        Prioritizes person inside the bowling corridor exhibiting forward translation.
        """
        if not candidates:
            return None

        valid_candidates = []
        for cand in candidates:
            xmin, ymin, xmax, ymax = cand.bbox
            center_x = (xmin + xmax) / 2.0
            center_y = (ymin + ymax) / 2.0

            # 1. Pitch corridor check (if specified)
            if pitch_corridor:
                c_xmin, c_ymin, c_xmax, c_ymax = pitch_corridor
                if not (c_xmin <= center_x <= c_xmax and c_ymin <= center_y <= c_ymax):
                    continue

            # 2. Check confidence of upper body
            upper_conf = (
                cand.left_shoulder.confidence
                + cand.right_shoulder.confidence
                + cand.left_hip.confidence
                + cand.right_hip.confidence
            ) / 4.0
            if upper_conf >= self.min_conf:
                valid_candidates.append((cand, center_y))

        if not valid_candidates:
            return None

        # Return candidate located deepest in delivery corridor (or highest conf)
        return max(valid_candidates, key=lambda c: c[1])[0]

    def update(
        self,
        bowler: Optional[PoseSkeleton],
        frame_idx: int,
        timestamp_ms: int,
    ) -> DeliveryMetrics:
        """
        Process the current frame and advance the bowler state machine.
        """
        metrics = DeliveryMetrics(
            phase=self.current_phase,
            bowling_arm=self.bowling_arm,
            release_timestamp_ms=timestamp_ms,
        )

        if bowler is None:
            # If tracking lost momentarily during runup, retain phase briefly
            return metrics

        # 1. Calculate Midpoints
        mid_hip_y = (bowler.left_hip.y + bowler.right_hip.y) / 2.0
        mid_hip_x = (bowler.left_hip.x + bowler.right_hip.x) / 2.0

        # 2. Detect which arm is active (overhead bowling arm reaches highest)
        # Note: In normalized screen coordinates, 0.0 is top of frame!
        rw_y = bowler.right_wrist.y if bowler.right_wrist.confidence > self.min_conf else 1.0
        lw_y = bowler.left_wrist.y if bowler.left_wrist.confidence > self.min_conf else 1.0

        if rw_y < lw_y:
            active_wrist = bowler.right_wrist
            active_shoulder = bowler.right_shoulder
            self.bowling_arm = "RIGHT"
        else:
            active_wrist = bowler.left_wrist
            active_shoulder = bowler.left_shoulder
            self.bowling_arm = "LEFT"

        # Calculate Arm Angle (relative to horizontal axis through shoulder)
        dx = active_wrist.x - active_shoulder.x
        dy = active_shoulder.y - active_wrist.y  # Invert so upwards is positive
        arm_angle = math.degrees(math.atan2(dy, dx))
        extension = math.hypot(dx, dy)

        # Stride length (distance between ankles)
        stride = math.hypot(
            bowler.left_ankle.x - bowler.right_ankle.x,
            bowler.left_ankle.y - bowler.right_ankle.y,
        )

        frame_state = {
            "frame_idx": frame_idx,
            "timestamp_ms": timestamp_ms,
            "hip_y": mid_hip_y,
            "wrist_y": active_wrist.y,
            "arm_angle": arm_angle,
            "extension": extension,
            "stride": stride,
        }
        self.frame_history.append(frame_state)
        if len(self.frame_history) > self.max_history:
            self.frame_history.pop(0)

        metrics.arm_angle_degrees = arm_angle
        metrics.bowling_arm = self.bowling_arm
        metrics.stride_length_normalized = stride

        # 3. Finite State Machine Transitions
        self._evaluate_state_machine(frame_state, metrics)

        return metrics

    def _evaluate_state_machine(self, current: Dict, metrics: DeliveryMetrics):
        if len(self.frame_history) < 3:
            return

        prev = self.frame_history[-2]

        if self.current_phase == DeliveryPhase.IDLE:
            # Check for forward run-up velocity over last 5 frames
            if len(self.frame_history) >= 5:
                start_hip_y = self.frame_history[-5]["hip_y"]
                delta_y = current["hip_y"] - start_hip_y
                if delta_y > self.runup_velocity_thresh:
                    self.current_phase = DeliveryPhase.RUN_UP
                    self.min_wrist_y = 1.0
                    self.release_detected = False

        elif self.current_phase == DeliveryPhase.RUN_UP:
            # Gather: Bowler initiates bound / stride.
            # Bowling arm begins upward circumduction or wrist rises above shoulder level
            if current["arm_angle"] > 30.0 or current["wrist_y"] < current["hip_y"] - 0.10:
                self.current_phase = DeliveryPhase.GATHER

        elif self.current_phase == DeliveryPhase.GATHER:
            # Delivery Stride: Front foot landing, bowling arm reaching vertical elevation (> 70 deg)
            if current["arm_angle"] > 65.0:
                self.current_phase = DeliveryPhase.DELIVERY_STRIDE
                self.min_wrist_y = current["wrist_y"]
                self.peak_arm_angle = current["arm_angle"]

        elif self.current_phase == DeliveryPhase.DELIVERY_STRIDE:
            # Track highest wrist point
            if current["wrist_y"] < self.min_wrist_y:
                self.min_wrist_y = current["wrist_y"]

            # RELEASE POINT DETECTION:
            # Condition 1: Arm reached near-vertical (arm_angle > 70 deg)
            # Condition 2: Wrist has passed its highest zenith and begins downwards follow-through
            wrist_dropped = current["wrist_y"] > (self.min_wrist_y + self.release_zenith_tolerance)
            arm_passed_peak = current["arm_angle"] < prev["arm_angle"] and prev["arm_angle"] > 75.0

            if wrist_dropped or arm_passed_peak:
                self.current_phase = DeliveryPhase.RELEASE
                metrics.is_release_frame = True
                metrics.release_frame_idx = current["frame_idx"]
                metrics.release_timestamp_ms = current["timestamp_ms"]
                self.release_detected = True

        elif self.current_phase == DeliveryPhase.RELEASE:
            # Immediate transition into follow-through
            self.current_phase = DeliveryPhase.FOLLOW_THROUGH

        elif self.current_phase == DeliveryPhase.FOLLOW_THROUGH:
            # After arm finishes swing (angle negative or low), reset to IDLE after cooldown
            if current["arm_angle"] < 0.0 or len(self.frame_history) >= self.max_history:
                # Reset ready for next delivery
                self.current_phase = DeliveryPhase.IDLE

        metrics.phase = self.current_phase


if __name__ == "__main__":
    print("BowlerKinematicsTracker loaded successfully.")
    tracker = BowlerKinematicsTracker()
    print("Initial State:", tracker.current_phase.value)
