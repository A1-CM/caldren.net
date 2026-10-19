New Caldren {{ ($inquiry['inquiry_type'] ?? 'general') === 'campus_sales' ? 'Campus sales inquiry' : 'contact inquiry' }} #{{ $leadId }}

Name: {{ $inquiry['name'] }}
Email: {{ $inquiry['email'] }}
Company: {{ $inquiry['company'] ?? 'Not provided' }}

@if(($inquiry['inquiry_type'] ?? 'general') === 'campus_sales')
Estimated buildings: {{ $inquiry['estimated_building_count'] }}
Operating regions: {{ $inquiry['operating_regions'] }}
Available energy data: {{ str_replace('_', ' ', $inquiry['available_energy_data']) }}
Goals: {{ $inquiry['goals'] }}
Target timeline: {{ str_replace('_', ' ', $inquiry['target_timeline']) }}

Additional context:
{{ $inquiry['message'] ?: 'Not provided' }}
@else
Message:
{{ $inquiry['message'] }}
@endif
