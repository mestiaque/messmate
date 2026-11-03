<span class="badge text-bg-{{ ['active' => 'success', 'suspended' => 'danger'][$tenant->status->value] ?? 'secondary' }}">{{ ucfirst($tenant->status->value) }}</span>
