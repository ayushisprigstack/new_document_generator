<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ManageLeads extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */

     public $property;
     public $leadsIssues;
     public $propertyImageUrl;
     public $csvFilePath;

    public function __construct($property,  $leadsIssues , $csvFilePath)
    {
        //
        $this->property = $property;
        $this->leadsIssues = $leadsIssues;
        $this->csvFilePath = $csvFilePath;



        // Check if the property has an image
        $propertyImagePath = public_path("{$property->property_img}");
        
        // Set the property image or default logo
        if (file_exists($propertyImagePath) && $property->property_img) {
            $this->propertyImageUrl = asset("{$property->property_img}");
        } else {
            $this->propertyImageUrl = asset('real-estate-logo.svg');
        }
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Leads Import: Review Skipped or Failed Entries',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.manage_leads',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [
            \Illuminate\Mail\Attachment::fromPath($this->csvFilePath), 
        ];
    }
}
