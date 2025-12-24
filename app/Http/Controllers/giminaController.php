<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use Illuminate\Container\Attributes\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log as FacadesLog;
use Symfony\Component\Console\Descriptor\Descriptor;

class giminaController extends Controller
{
    public function detals(Request $request)
    {
      
        $title_text = "write titel of my youtube video its video about white noise for baby make me titel to get most views based on youtube algrtiom giv me just the titel in replay message without any thing";

        $response_title = $this->base($title_text);

        $description_text = "write description for this titel $response_title its video about white noise for baby make me description to get most views based on youtube algrtiom giv me just the description in replay message without any thing";
        $response_description = $this->base($description_text);

        return response()->json([
            'title' => $response_title,
            'description' => $response_description,
        ]);
    }

    
    public function base($text)
    {
        // Replace with your actual API key
        $apiKey = 'AIzaSyACcDPq0OiAACUfpWZ55cRKjr_NoD5qIWY';

        // Create a Guzzle HTTP client
        $client = new Client();

        // Define the API endpoint
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}";

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
