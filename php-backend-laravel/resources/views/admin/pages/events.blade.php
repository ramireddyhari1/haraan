@extends('admin.partials.layout')

@section('content')
@php
    // Fetch live system metrics for the Command Center
    $totalEvents = \App\Models\Event::count();
    $pendingApprovals = \App\Models\Event::whereIn('status', ['PENDING', 'DRAFT', 'pending', 'draft'])->count();
    $soldTickets = (int) \App\Models\Booking::whereIn('status', ['PAID', 'CONFIRMED', 'paid', 'confirmed'])->sum('quantity');
    $revenue = (float) \App\Models\Booking::whereIn('status', ['PAID', 'CONFIRMED', 'paid', 'confirmed'])->sum('total_amount');
    $activeStaff = \App\Models\User::where('role', 'WORKER')->count();
    
    // Fetch recent administrative logs for the bottom audit feed
    $recentActivities = \App\Models\AdminAction::with('user')
        ->orderByDesc('created_at')
        ->limit(5)
        ->get();
@endphp

<!-- Command Center Header -->
<section class="card hero" style="margin-bottom:14px; background: linear-gradient(135deg, #fffcf9 0%, #fff7f2 100%); border-left: 5px solid var(--brand);">
	<span class="eyebrow">Operational Command Center</span>
	<h2 style="margin: 0; font-size: 26px;">Event Control Center</h2>
	<p style="margin: 0; font-size: 14px;">Monitor real-time ticket sales, capacity slots, worker allocations, and approve partner program registrations.</p>
</section>

<!-- KPIs Stats Row -->
<div class="grid-5" style="margin-bottom: 14px;">
    <div class="card metric" style="border-top: 3px solid var(--brand);">
        <span class="label">Total Events</span>
        <div class="value">{{ $totalEvents }}</div>
        <span class="note">Across all categories</span>
    </div>
    <div class="card metric" style="border-top: 3px solid var(--brand-2);">
        <span class="label">Pending Review</span>
        <div class="value" style="color: #c2841e;">{{ $pendingApprovals }}</div>
        <span class="note">Awaiting publication</span>
    </div>
    <div class="card metric" style="border-top: 3px solid #16a34a;">
        <span class="label">Sold Tickets</span>
        <div class="value" style="color: #16a34a;">{{ $soldTickets }}</div>
        <span class="note">Paid & Confirmed seats</span>
    </div>
    <div class="card metric" style="border-top: 3px solid #2563eb;">
        <span class="label">Total Revenue</span>
        <div class="value" style="color: #2563eb;">₹{{ number_format($revenue, 2) }}</div>
        <span class="note">Stripe/Razorpay volume</span>
    </div>
    <div class="card metric" style="border-top: 3px solid #6b7280;">
        <span class="label">Active Staff</span>
        <div class="value">{{ $activeStaff }}</div>
        <span class="note">Assigned workers & hosts</span>
    </div>
</div>

<!-- Main Split Grid -->
<div style="display: grid; grid-template-columns: 2.2fr 1fr; gap: 14px; margin-bottom: 14px;">
    
    <!-- Left Column: Event Table -->
    <section class="card" style="padding: 16px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
            <h3 style="margin: 0;">Active Events</h3>
            <div style="display: flex; gap: 8px; align-items: center;">
                <input type="text" id="event-search" placeholder="Search events..." style="padding: 6px 10px; font-size: 13px; border: 1px solid var(--line); border-radius: 8px; width: 180px;">
                <select id="status-filter" style="padding: 6px 10px; font-size: 13px; border: 1px solid var(--line); border-radius: 8px; background: white;">
                    <option value="ALL">All Statuses</option>
                    <option value="LIVE">Live / Active</option>
                    <option value="PENDING">Pending Review</option>
                    <option value="DRAFT">Draft</option>
                </select>
            </div>
        </div>
        <div style="overflow-x: auto;">
            <table class="table" id="events-table">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Status</th>
                        <th>Tickets</th>
                        <th>Revenue</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="events-table-body">
                    <tr>
                        <td colspan="6" class="placeholder">Loading event directories…</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Right Column: Operations Rail -->
    <section class="card" style="padding: 16px; background: #fafaf9;">
        <h3 style="margin-top: 0; margin-bottom: 12px;">Quick Operations</h3>
        <div class="list" style="margin-bottom: 14px;">
            <div class="list-item" style="padding: 8px 12px; background: white; border-radius: 8px; margin-bottom: 8px; border: 1px solid var(--line);">
                <span style="font-weight: 700; font-size:13px; display:block;">Operational Control</span>
                <span style="font-size:11px; color:var(--muted);">Approve new tournaments, assign worker staff, and reconcile booking fees.</span>
            </div>
        </div>
        <div style="display: grid; gap: 8px;">
            <a class="action" href="{{ route('admin.events.create') }}" style="justify-content: center;">+ Create Event</a>
            <a class="action action--ghost" href="{{ route('admin.workers') }}" style="justify-content: center;">Assign Staff</a>
            <button class="action action--ghost" id="quick-action-approve" style="justify-content: center; cursor: pointer; text-align: center; border-radius: 10px; font-weight: 700;">Pending Approvals</button>
            <a class="action action--ghost" href="{{ route('admin.export.bookings') }}" style="justify-content: center;">Export Bookings</a>
        </div>
    </section>

</div>

<!-- Bottom Row: Activity Feed -->
<section class="card" style="padding: 16px;">
    <h3 style="margin-top:0; margin-bottom:12px;">Recent Audit logs</h3>
    <div class="list">
        @forelse($recentActivities as $activity)
            <div class="list-item" style="padding: 10px 14px; background: #fffdfb; display: flex; justify-content: space-between; align-items: center; border-color: rgba(194, 132, 30, 0.12);">
                <div>
                    <strong style="font-size:14px; color: var(--brand);">{{ $activity->action }}</strong>
                    <span style="font-size: 11px; color: var(--muted); margin-top: 2px; display:block;">
                        Actor: {{ $activity->user?->name ?? 'System' }} (IP: {{ $activity->ip }})
                    </span>
                </div>
                <div style="font-size: 12px; color: var(--muted); font-weight: 600;">
                    {{ $activity->created_at->diffForHumans() }}
                </div>
            </div>
        @empty
            <div class="placeholder">No administrative activity records found.</div>
        @endforelse
    </div>
</section>

<script>
	(function(){
		const tbody = document.getElementById('events-table-body');
		const searchInput = document.getElementById('event-search');
		const statusFilter = document.getElementById('status-filter');
		const quickActionApprove = document.getElementById('quick-action-approve');
		
		let eventsList = [];

		function renderEvents(filteredEvents) {
			tbody.innerHTML = '';
			if (filteredEvents.length === 0) {
				tbody.innerHTML = `
					<tr>
						<td colspan="6">
							<div style="text-align: center; padding: 40px 20px;">
								<h4 style="margin: 0 0 8px; font-size: 16px;">No Events Found</h4>
								<p style="color: var(--muted); font-size: 13px; margin: 0 0 16px;">Create a new sports tournament or change your filters to view active listings.</p>
								<a class="action" href="{{ route('admin.events.create') }}">+ Create First Event</a>
							</div>
						</td>
					</tr>
				`;
				return;
			}

			filteredEvents.forEach(ev => {
				const tr = document.createElement('tr');
				
				const statusVal = (ev.status || 'draft').toUpperCase();
				let badgeColor = '#64748b';
				let badgeBg = '#f1f5f9';
				if (statusVal === 'LIVE' || statusVal === 'ACTIVE') {
					badgeColor = '#16a34a';
					badgeBg = '#dcfce7';
				} else if (statusVal === 'PENDING' || statusVal === 'DRAFT') {
					badgeColor = '#d97706';
					badgeBg = '#fef3c7';
				}

				// Format Price
				const priceFormatted = ev.price > 0 ? `₹${parseFloat(ev.price).toFixed(2)}` : '<span style="color:#16a34a; font-weight:700;">FREE</span>';
				
				// Format Date
				let dateStr = '—';
				if (ev.date) {
					const d = new Date(ev.date);
					if (!isNaN(d.getTime())) {
						dateStr = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
					}
				}

				tr.innerHTML = `
					<td>
						<div style="font-weight: 700; font-size: 14px; color: var(--text);">${ev.title}</div>
						<div style="font-size: 11px; color: var(--muted); margin-top: 2px;">
							<span style="text-transform: uppercase; font-weight: 600; color: var(--brand); margin-right: 6px;">${ev.category || 'General'}</span>
							• <span style="margin-left: 6px;">${ev.venue || '—'}</span>
						</div>
					</td>
					<td>
						<span class="muted-chip" style="background:${badgeBg}; color:${badgeColor}; border:none; padding:4px 10px; font-size:11px;">${statusVal}</span>
					</td>
					<td>
						<div style="font-weight: 600; font-size: 13px;">${ev.tickets_sold || 0} sold</div>
						<div style="font-size: 11px; color: var(--muted); margin-top: 2px;">
							Cap: ${ev.total_slots > 0 ? ev.total_slots : 'Unlimited'}
						</div>
					</td>
					<td>
						<div style="font-weight: 700; color: #16a34a; font-size: 13px;">
							₹${parseFloat(ev.revenue || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
						</div>
						<div style="font-size: 11px; color: var(--muted); margin-top: 2px;">
							Price: ${priceFormatted}
						</div>
					</td>
					<td>
						<div style="font-weight: 600; font-size: 13px; color: #334155;">${dateStr}</div>
						<div style="font-size: 11px; color: var(--muted); margin-top: 2px;">${ev.time || '—'}</div>
					</td>
				`;

				// Actions column
				const actionsTd = document.createElement('td');
				actionsTd.style.display = 'flex';
				actionsTd.style.gap = '8px';
				actionsTd.style.alignItems = 'center';
				
				const editBtn = document.createElement('a');
				editBtn.href = '{{ url('/admin/events') }}/' + ev.id + '/edit';
				editBtn.className = 'btn';
				editBtn.style.padding = '4px 8px';
				editBtn.style.fontSize = '12px';
				editBtn.style.textDecoration = 'none';
				editBtn.textContent = 'Edit';
				actionsTd.appendChild(editBtn);

				// If Pending Approval, show Approve Button
				if (statusVal === 'PENDING' || statusVal === 'DRAFT') {
					const approveBtn = document.createElement('button');
					approveBtn.className = 'btn';
					approveBtn.style.padding = '4px 8px';
					approveBtn.style.fontSize = '12px';
					approveBtn.style.background = '#16a34a';
					approveBtn.textContent = 'Approve';
					approveBtn.onclick = function() {
						if (!confirm('Are you sure you want to approve and publish this event?')) return;
						fetch('{{ url('/admin/events') }}/' + ev.id, { 
							method: 'PUT',
							headers: {
								'Content-Type': 'application/json',
								'X-CSRF-TOKEN': '{{ csrf_token() }}'
							},
							body: JSON.stringify({ status: 'ACTIVE' })
						})
						.then(r => r.json())
						.then(j => { 
							alert(j.message || 'Approved successfully'); 
							location.reload(); 
						})
						.catch(e => alert('Failed to approve event'));
					};
					actionsTd.appendChild(approveBtn);
				}

				const delBtn = document.createElement('button');
				delBtn.className = 'btn';
				delBtn.style.padding = '4px 8px';
				delBtn.style.fontSize = '12px';
				delBtn.style.background = '#ef4444';
				delBtn.textContent = 'Delete';
				delBtn.onclick = function(){
					if (!confirm('Are you sure you want to delete this event?')) return;
					fetch('{{ url('/admin/events') }}/' + ev.id, { 
						method: 'DELETE', 
						headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}' } 
					})
					.then(r => r.json())
					.then(j => { 
						alert(j.message || 'Deleted successfully'); 
						location.reload(); 
					})
					.catch(e => alert('Failed to delete event'));
				};
				actionsTd.appendChild(delBtn);
				
				tr.appendChild(actionsTd);
				tbody.appendChild(tr);
			});
		}

		function applyFilters() {
			const query = searchInput.value.toLowerCase().trim();
			const status = statusFilter.value;

			const filtered = eventsList.filter(ev => {
				const matchesQuery = ev.title.toLowerCase().includes(query) || 
									 (ev.category && ev.category.toLowerCase().includes(query)) ||
									 (ev.venue && ev.venue.toLowerCase().includes(query));
				
				const statusVal = (ev.status || 'draft').toUpperCase();
				let matchesStatus = true;
				if (status === 'LIVE') {
					matchesStatus = (statusVal === 'LIVE' || statusVal === 'ACTIVE');
				} else if (status === 'PENDING') {
					matchesStatus = (statusVal === 'PENDING');
				} else if (status === 'DRAFT') {
					matchesStatus = (statusVal === 'DRAFT');
				}

				return matchesQuery && matchesStatus;
			});

			renderEvents(filtered);
		}

		// Event listeners
		searchInput.addEventListener('input', applyFilters);
		statusFilter.addEventListener('change', applyFilters);

		quickActionApprove.addEventListener('click', function(e) {
			e.preventDefault();
			statusFilter.value = 'PENDING';
			applyFilters();
		});

		fetch('{{ route('admin.events.json') }}')
			.then(r => r.json())
			.then(data => {
				eventsList = data.data || [];
				applyFilters();
			}).catch(err => {
				tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="placeholder" style="color:#ef4444; background:#fef2f2; border-color:#fee2e2;">
                            Failed to load events directory. Please check database connectivity.
                        </td>
                    </tr>
                `;
				console.error(err);
			});
	})();
</script>
@endsection

