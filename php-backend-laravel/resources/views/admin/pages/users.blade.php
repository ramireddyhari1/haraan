@extends('admin.partials.layout')

@section('content')
<!-- Executive Command Strip -->
<div style="display: grid; grid-template-columns: repeat(6, 1fr); gap: 12px; margin-bottom: 14px;">
    <div class="card metric" style="border-top: 3px solid var(--brand); padding: 12px 16px;">
        <span class="label">Total Users</span>
        <div class="value" style="font-size: 24px; margin-top: 4px;">{{ number_format($stats['total']) }}</div>
        <span class="note" style="font-size: 11px;">Registered accounts</span>
    </div>
    <div class="card metric" style="border-top: 3px solid #16a34a; padding: 12px 16px;">
        <span class="label">Active Today</span>
        <div class="value" style="font-size: 24px; margin-top: 4px; color: #16a34a;">{{ number_format($stats['active_today']) }}</div>
        <span class="note" style="font-size: 11px;">Interacted in 24h</span>
    </div>
    <div class="card metric" style="border-top: 3px solid #2563eb; padding: 12px 16px;">
        <span class="label">New Today</span>
        <div class="value" style="font-size: 24px; margin-top: 4px; color: #2563eb;">{{ number_format($stats['new_today']) }}</div>
        <span class="note" style="font-size: 11px;">Fresh registrations</span>
    </div>
    <div class="card metric" style="border-top: 3px solid var(--brand-2); padding: 12px 16px;">
        <span class="label">Partners</span>
        <div class="value" style="font-size: 24px; margin-top: 4px; color: var(--brand-2);">{{ number_format($stats['partners']) }}</div>
        <span class="note" style="font-size: 11px;">Organizers & Hosts</span>
    </div>
    <div class="card metric" style="border-top: 3px solid #6b7280; padding: 12px 16px;">
        <span class="label">Staff</span>
        <div class="value" style="font-size: 24px; margin-top: 4px;">{{ number_format($stats['staff']) }}</div>
        <span class="note" style="font-size: 11px;">Admins & Workers</span>
    </div>
    <div class="card metric" style="border-top: 3px solid #ef4444; padding: 12px 16px;">
        <span class="label">Suspended</span>
        <div class="value" style="font-size: 24px; margin-top: 4px; color: #ef4444;">{{ number_format($stats['suspended']) }}</div>
        <span class="note" style="font-size: 11px;">Banned accounts</span>
    </div>
</div>

<!-- Main Split Grid -->
<div style="display: grid; grid-template-columns: 1.1fr 3fr; gap: 14px; margin-bottom: 14px;">
    
    <!-- Left Column: Operations & Risk Sidebar -->
    <div style="display: flex; flex-direction: column; gap: 14px;">
        
        <!-- Platform Health Card -->
        <div class="card" style="padding: 16px; background: linear-gradient(135deg, #fffdfb 0%, #fff7f2 100%); border-left: 4px solid var(--brand-2);">
            <h3 style="margin-top: 0; margin-bottom: 12px; font-size: 14px; color: var(--brand); text-transform: uppercase; letter-spacing: 0.05em;">Platform Health</h3>
            <div style="display: flex; align-items: baseline; gap: 6px; margin-bottom: 12px;">
                <span style="font-size: 34px; font-weight: 800; color: {{ $health_score >= 80 ? '#16a34a' : ($health_score >= 60 ? '#c2841e' : '#ef4444') }}">{{ $health_score }}</span>
                <span style="color: var(--muted); font-size: 14px; font-weight: 600;">/ 100</span>
            </div>
            <div style="font-size: 12px; display: grid; gap: 8px;">
                <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed var(--line); padding-bottom: 6px;">
                    <span style="color: var(--muted);">Revenue Loop:</span>
                    <strong style="color: #16a34a;">Healthy</strong>
                </div>
                <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed var(--line); padding-bottom: 6px;">
                    <span style="color: var(--muted);">User Activity:</span>
                    <strong style="color: #16a34a;">Healthy</strong>
                </div>
                <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed var(--line); padding-bottom: 6px;">
                    <span style="color: var(--muted);">Approvals backlog:</span>
                    <strong style="color: {{ ($risk['pending_verifications'] + $approvals['partners']) > 5 ? '#ef4444' : '#16a34a' }}">
                        {{ ($risk['pending_verifications'] + $approvals['partners']) > 5 ? 'Delayed' : 'Optimal' }}
                    </strong>
                </div>
                <div style="display: flex; justify-content: space-between; padding-bottom: 2px;">
                    <span style="color: var(--muted);">Suspicious Users:</span>
                    <strong style="color: {{ $risk['suspicious'] > 0 ? '#ef4444' : '#16a34a' }}">
                        {{ $risk['suspicious'] > 0 ? 'Review Needed' : 'None' }}
                    </strong>
                </div>
            </div>
        </div>

        <!-- Risk Center -->
        <div class="card" style="padding: 16px;">
            <h3 style="margin-top: 0; margin-bottom: 12px; font-size: 14px; text-transform: uppercase; letter-spacing: 0.05em; display: flex; justify-content: space-between; align-items: center;">
                <span>Risk Center</span>
                @if($risk['suspicious'] > 0)
                    <span style="background: #fee2e2; color: #ef4444; font-size: 9px; padding: 2px 6px; border-radius: 999px; font-weight: 800;">ALERT</span>
                @endif
            </h3>
            <div style="font-size: 12px; display: grid; gap: 10px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: var(--muted);">Suspicious Accounts</span>
                    <span style="background: {{ $risk['suspicious'] > 0 ? '#fee2e2' : '#f1f5f9' }}; font-weight:700; padding: 2px 8px; border-radius: 6px; color: {{ $risk['suspicious'] > 0 ? '#ef4444' : '#475569' }}">{{ $risk['suspicious'] }}</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: var(--muted);">Pending Verifications</span>
                    <span style="background: #fef3c7; font-weight:700; padding: 2px 8px; border-radius: 6px; color: #d97706;">{{ $risk['pending_verifications'] }}</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: var(--muted);">Reported Organizers</span>
                    <span style="background: {{ $risk['reported_organizers'] > 0 ? '#fee2e2' : '#f1f5f9' }}; font-weight:700; padding: 2px 8px; border-radius: 6px; color: {{ $risk['reported_organizers'] > 0 ? '#ef4444' : '#475569' }}">{{ $risk['reported_organizers'] }}</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: var(--muted);">Failed Payments</span>
                    <span style="background: #f1f5f9; font-weight:700; padding: 2px 8px; border-radius: 6px; color: #475569;">{{ $risk['failed_payments'] }}</span>
                </div>
            </div>
        </div>

        <!-- Approval Center -->
        <div class="card" style="padding: 16px;">
            <h3 style="margin-top: 0; margin-bottom: 12px; font-size: 14px; text-transform: uppercase; letter-spacing: 0.05em;">Approval Center</h3>
            <div style="font-size: 12px; display: grid; gap: 10px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: var(--muted);">Partner Applications</span>
                    <span style="background: #fbefe6; color: var(--brand); font-weight:700; padding: 2px 8px; border-radius: 6px;">{{ $approvals['partners'] }}</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: var(--muted);">Worker Requests</span>
                    <span style="background: #f1f5f9; font-weight:700; padding: 2px 8px; border-radius: 6px; color: #475569;">{{ $approvals['workers'] }}</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: var(--muted);">Role Changes</span>
                    <span style="background: #f1f5f9; font-weight:700; padding: 2px 8px; border-radius: 6px; color: #475569;">{{ $approvals['role_changes'] }}</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: var(--muted);">Venue Verifications</span>
                    <span style="background: #f1f5f9; font-weight:700; padding: 2px 8px; border-radius: 6px; color: #475569;">{{ $approvals['venues'] }}</span>
                </div>
            </div>
        </div>

    </div>

    <!-- Right Column: User Intelligence Table -->
    <div class="card" style="padding: 16px;">
        <!-- Search & Filter Header -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
            <h3 style="margin: 0;">User Intelligence</h3>
            <div style="display: flex; gap: 8px; align-items: center;">
                <input type="text" id="user-search" placeholder="Search name or email..." style="padding: 6px 10px; font-size: 13px; border: 1px solid var(--line); border-radius: 8px; width: 200px;">
                <select id="role-filter" style="padding: 6px 10px; font-size: 13px; border: 1px solid var(--line); border-radius: 8px; background: white;">
                    <option value="">All Roles</option>
                    <option value="ADMIN">Admin</option>
                    <option value="COADMIN">Co-Admin</option>
                    <option value="PARTNER">Partner</option>
                    <option value="WORKER">Worker</option>
                    <option value="USER">Regular User</option>
                </select>
            </div>
        </div>

        <!-- Role Assignment Quick Box -->
        <div style="display: flex; gap: 10px; align-items: center; background: #fafaf9; padding: 8px 12px; border-radius: 10px; margin-bottom: 12px; border: 1px solid var(--line); flex-wrap: wrap;">
            <span style="font-size: 12px; font-weight: 700; color: var(--brand);">Quick Role Assign:</span>
            <select id="role-select" style="padding: 4px 8px; font-size: 12px; border: 1px solid var(--line); border-radius: 6px; background: white;"></select>
            <button class="btn" id="apply-role" style="padding: 4px 10px; font-size: 12px;">Update Selected User Role</button>
            <span style="font-size: 11px; color: var(--muted); margin-left: auto;">Select a radio button in the table to assign roles.</span>
        </div>

        <!-- Dynamic Users Table -->
        <div style="overflow-x: auto;">
            <table class="table" id="users-table">
                <thead>
                    <tr>
                        <th style="width: 40px; text-align: center;">Sel</th>
                        <th>User</th>
                        <th>Role</th>
                        <th>Organization</th>
                        <th>Bookings</th>
                        <th>Revenue</th>
                        <th>Trust</th>
                        <th>Last Active</th>
                        <th>Risk</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="users-body">
                    <tr>
                        <td colspan="10" class="placeholder">Loading user directories…</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination Bar -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 14px; font-size: 12px; border-top: 1px solid var(--line); padding-top: 12px;">
            <span id="pagination-info" style="color: var(--muted); font-weight: 600;">Showing 0 to 0 of 0 entries</span>
            <div style="display: flex; gap: 6px;">
                <button class="btn" id="btn-prev" style="padding: 4px 8px; font-size: 11px;" disabled>Previous</button>
                <button class="btn" id="btn-next" style="padding: 4px 8px; font-size: 11px;" disabled>Next</button>
            </div>
        </div>

    </div>

</div>

<!-- Bottom Row: Live SOC Activity Feed -->
<section class="card" style="padding: 16px;">
    <h3 style="margin-top:0; margin-bottom:12px; font-size: 15px; color: var(--brand); text-transform: uppercase; letter-spacing: 0.05em;">Live Security Operations Feed</h3>
    <div class="list">
        @forelse($recent_activities as $activity)
            <div class="list-item" style="padding: 10px 14px; background: #fffdfb; display: flex; justify-content: space-between; align-items: center; border-color: rgba(194, 132, 30, 0.12);">
                <div>
                    <strong style="font-size:13px; color: var(--brand);">{{ $activity->action }}</strong>
                    <span style="font-size: 11px; color: var(--muted); margin-top: 2px; display:block;">
                        Actor: {{ $activity->user?->name ?? 'System' }} (IP: {{ $activity->ip }})
                    </span>
                </div>
                <div style="font-size: 12px; color: var(--muted); font-weight: 600;">
                    {{ $activity->created_at->diffForHumans() }}
                </div>
            </div>
        @empty
            <div class="placeholder">No administrative activity records found in security audits.</div>
        @endforelse
    </div>
</section>
@endsection

@section('scripts')
<script>
	(function() {
		const tbody = document.getElementById('users-body');
		const searchInput = document.getElementById('user-search');
		const roleFilter = document.getElementById('role-filter');
		const roleSelect = document.getElementById('role-select');
		const applyRoleBtn = document.getElementById('apply-role');
		const btnPrev = document.getElementById('btn-prev');
		const btnNext = document.getElementById('btn-next');
		const paginationInfo = document.getElementById('pagination-info');

		let currentPage = 1;
		let searchQuery = '';
		let roleQuery = '';
		let searchTimeout = null;

		async function loadUsers(page = 1) {
			currentPage = page;
			let url = `{{ route('admin.users.json') }}?page=${page}`;
			if (searchQuery) url += `&q=${encodeURIComponent(searchQuery)}`;
			if (roleQuery) url += `&role=${encodeURIComponent(roleQuery)}`;

			try {
				const res = await fetch(url);
				const json = await res.json();
				const paginator = json.data;
				const users = paginator.data || [];

				// Update Pagination Details
				paginationInfo.textContent = `Showing ${paginator.from || 0} to ${paginator.to || 0} of ${paginator.total || 0} entries`;
				btnPrev.disabled = (paginator.current_page <= 1);
				btnNext.disabled = (paginator.current_page >= paginator.last_page);

				tbody.innerHTML = '';
				if (users.length === 0) {
					tbody.innerHTML = `
						<tr>
							<td colspan="10" class="placeholder" style="text-align: center; padding: 30px;">
								No matching users found inside workspace directories.
							</td>
						</tr>
					`;
					return;
				}

				users.forEach(u => {
					const tr = document.createElement('tr');

					// Style role chip
					const roleVal = (u.role || 'USER').toUpperCase();
					let roleBg = '#f1f5f9';
					let roleColor = '#475569';
					if (roleVal === 'ADMIN' || roleVal === 'SUPER ADMIN') {
						roleBg = '#fee2e2';
						roleColor = '#ef4444';
					} else if (roleVal === 'COADMIN') {
						roleBg = '#fef3c7';
						roleColor = '#d97706';
					} else if (roleVal === 'PARTNER') {
						roleBg = '#fbefe6';
						roleColor = 'var(--brand)';
					} else if (roleVal === 'WORKER') {
						roleBg = '#e0f2fe';
						roleColor = '#0284c7';
					}

					// Style trust indicator
					const trustVal = parseInt(u.trust_score_value || 100);
					let trustColor = '#16a34a';
					if (trustVal < 50) {
						trustColor = '#ef4444';
					} else if (trustVal < 80) {
						trustColor = '#d97706';
					}

					// Style risk badge
					const riskVal = u.risk_level || 'Low';
					let riskBg = '#dcfce7';
					let riskColor = '#16a34a';
					if (riskVal.includes('High')) {
						riskBg = '#fee2e2';
						riskColor = '#ef4444';
					} else if (riskVal.includes('Medium')) {
						riskBg = '#fef3c7';
						riskColor = '#d97706';
					}

					tr.innerHTML = `
						<td style="text-align: center; vertical-align: middle;">
							<input type="radio" name="selected-user" value="${u.id}" style="cursor: pointer; transform: scale(1.1);"/>
						</td>
						<td>
							<div style="display: flex; align-items: center; gap: 8px;">
								<div style="width: 32px; height: 32px; border-radius: 999px; background: var(--brand-soft); color: var(--brand); font-weight: 700; display: grid; place-items: center; font-size: 12px; border: 1px solid rgba(139, 30, 63, 0.12);">
									${(u.name || 'U').substring(0, 2).toUpperCase()}
								</div>
								<div>
									<div style="font-weight: 700; color: var(--text); font-size: 13px;">${u.name}</div>
									<div style="font-size: 11px; color: var(--muted);">${u.email}</div>
								</div>
							</div>
						</td>
						<td>
							<span class="muted-chip" style="background:${roleBg}; color:${roleColor}; border:none; padding:4px 10px; font-size:11px;">${roleVal}</span>
						</td>
						<td><span style="font-size: 12px; font-weight: 600; color: #475569;">${u.organizations_list || '—'}</span></td>
						<td><span style="font-weight: 600; font-size: 13px;">${u.bookings_count || 0}</span></td>
						<td>
							<span style="font-weight: 700; color: #16a34a; font-size: 13px;">
								₹${parseFloat(u.revenue || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
							</span>
						</td>
						<td>
							<div style="display: flex; align-items: center; gap: 6px;">
								<span style="font-weight: 700; color: ${trustColor}; font-size: 12px;">${trustVal}</span>
								<div style="width: 40px; height: 4px; background: #e2e8f0; border-radius: 2px; overflow: hidden;">
									<div style="width: ${trustVal}%; height: 100%; background: ${trustColor};"></div>
								</div>
							</div>
						</td>
						<td><span style="font-size: 12px; color: var(--muted);">${u.last_active_human}</span></td>
						<td>
							<span class="muted-chip" style="background:${riskBg}; color:${riskColor}; border:none; padding:4px 10px; font-size:11px; font-weight: 700;">${riskVal}</span>
						</td>
					`;

					// Actions column
					const actionsTd = document.createElement('td');
					const actionBtn = document.createElement('button');
					actionBtn.className = 'btn';
					actionBtn.style.padding = '4px 8px';
					actionBtn.style.fontSize = '12px';

					if (u.status === 'SUSPENDED') {
						actionBtn.textContent = 'Reactivate';
						actionBtn.style.background = '#16a34a';
						actionBtn.onclick = async () => {
							if (!confirm(`Are you sure you want to reactivate user "${u.name}"?`)) return;
							await fetch(`{{ url('/admin/users') }}/${u.id}/reactivate`, {
								method: 'POST',
								headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
							});
							loadUsers(currentPage);
						};
					} else {
						actionBtn.textContent = 'Suspend';
						actionBtn.style.background = '#ef4444';
						actionBtn.onclick = async () => {
							if (!confirm(`Are you sure you want to suspend user "${u.name}"?`)) return;
							await fetch(`{{ url('/admin/users') }}/${u.id}/suspend`, {
								method: 'POST',
								headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
							});
							loadUsers(currentPage);
						};
					}

					actionsTd.appendChild(actionBtn);
					tr.appendChild(actionsTd);
					tbody.appendChild(tr);
				});

			} catch(e) {
				tbody.innerHTML = `
					<tr>
						<td colspan="10" class="placeholder" style="color:#ef4444; background:#fef2f2; border-color:#fee2e2;">
							Failed to load users directory. Please check database connectivity.
						</td>
					</tr>
				`;
				console.error(e);
			}
		}

		async function loadRolesSelect() {
			try {
				const res = await fetch('{{ route('admin.roles.json') }}');
				const json = await res.json();
				const rows = json.data || json;
				roleSelect.innerHTML = '';
				rows.forEach(r => {
					const opt = document.createElement('option');
					opt.value = r.name;
					opt.text = r.name;
					roleSelect.appendChild(opt);
				});
			} catch(e) {
				console.error('Failed to load roles select options', e);
			}
		}

		// Apply role modification
		applyRoleBtn.addEventListener('click', async () => {
			const checkedRadio = document.querySelector('input[name=selected-user]:checked');
			const selectedRole = roleSelect.value;
			if (!checkedRadio) return alert('Please select a user from the table first.');
			if (!selectedRole) return alert('Please select a role to assign.');

			const userId = checkedRadio.value;
			try {
				const res = await fetch(`{{ url('admin/users') }}/${userId}/role`, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-CSRF-TOKEN': '{{ csrf_token() }}'
					},
					body: JSON.stringify({ role: selectedRole })
				});
				const j = await res.json();
				alert(j.message || 'Role assigned successfully.');
				loadUsers(currentPage);
			} catch(e) {
				alert('Failed to update user role.');
			}
		});

		// Filters & Search handlers
		searchInput.addEventListener('input', (e) => {
			clearTimeout(searchTimeout);
			searchQuery = e.target.value;
			searchTimeout = setTimeout(() => {
				loadUsers(1);
			}, 300);
		});

		roleFilter.addEventListener('change', (e) => {
			roleQuery = e.target.value;
			loadUsers(1);
		});

		btnPrev.addEventListener('click', () => {
			if (currentPage > 1) loadUsers(currentPage - 1);
		});

		btnNext.addEventListener('click', () => {
			loadUsers(currentPage + 1);
		});

		// Booting dashboard components
		document.addEventListener('DOMContentLoaded', () => {
			loadUsers(1);
			loadRolesSelect();
		});

	})();
</script>
@endsection
