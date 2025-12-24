<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use Illuminate\Container\Attributes\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log as FacadesLog;

class geminiController extends Controller
{
    //api_key = AIzaSyACcDPq0OiAACUfpWZ55cRKjr_NoD5qIWY

    public function titel(Request $request)
    {
      
      
        $title = "write titel of my youtube video its video about white noise for baby make me titel to get most views based on youtube algrtiom giv me just the titel in replay message without any thing";

        $response = $this->base($title);
        return response()->json([
            'title' => $response,
        ]);
    }
    public function base($text)
    {
        // Replace with your actual API key
        $apiKey = 'AIzaSyACcDPq0OiAACUfpWZ55cRKjr_NoD5qIWY';

        // Create a Guzzle HTTP client
        $client = new Client();

        // Define the API endpoint
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-pro-preview-05-06:generateContent?key={$apiKey}";

        // Prepare the request payload
        $payload = [
            "contents" => [
                [
                    "parts" => [
                        ["text" => $text ]
                    ]
                ]
            ]
        ];

        try {
            // Send the POST request
            $response = $client->post($url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);
            // Decode the response
            $responseBody = json_decode($response->getBody(), true);

            // Extract the title from the response
            $title = $responseBody['candidates'][0]['content']['parts'][0]['text'];
            return  trim($title);

          
            
        } catch (\Exception $e) {
            FacadesLog::error($e->getMessage());
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

}
