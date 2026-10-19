<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactInquiry extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{name: string, email: string, company?: string|null, message: string, inquiry_type?: string, estimated_building_count?: int, operating_regions?: string, available_energy_data?: string, goals?: string, target_timeline?: string}  $inquiry
     */
    public function __construct(public array $inquiry, public int $leadId) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->inquiry['email'], $this->inquiry['name'])],
            subject: ($this->inquiry['inquiry_type'] ?? 'general') === 'campus_sales' ? "New Caldren Campus sales inquiry #{$this->leadId}" : "New Caldren contact inquiry #{$this->leadId}",
        );
    }

    public function content(): Content
    {
        return new Content(text: 'site.contact-email');
    }
}
