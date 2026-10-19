New Caldren contact inquiry #{{ $leadId }}

Name: {{ $inquiry['name'] }}
Email: {{ $inquiry['email'] }}
Company: {{ $inquiry['company'] ?? 'Not provided' }}

Message:
{{ $inquiry['message'] }}
