<?php

namespace App\Services;

use Twilio\Rest\Client;

class WhatsAppService
{
    protected $client;
    protected $from;

    public function __construct()
    {
        $this->client = new Client(
            config('services.twilio.sid'),
            config('services.twilio.token')
        );
        $this->from = 'whatsapp:' . config('services.twilio.whatsapp_from');
    }

    public function sendMessage($to, $message)
    {
        $this->client->messages->create(
            'whatsapp:' . $this->formatNumber($to),
            [
                'from' => $this->from,
                'body' => $message
            ]
        );
    }

    protected function formatNumber($phone)
    {
        // Ensures correct format, ex: 2376XXXXXXX
        return preg_replace('/[^0-9]/', '', $phone);
    }
}
