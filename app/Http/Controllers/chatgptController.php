<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;

class chatgptController extends Controller
{
    public function sendMessage(Request $request)
    {
        // Validate the request
        // $request->validate([
        //     'message' => 'required|string',
        // ]);

        // OpenAI API key
        $apiKey = env('OPENAI_API_KEY');

        $url = "https://api.openai.com/v1/responses";

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer $apiKey",
        ])->post($url, [
            'model' => 'gpt-4.1',
            'input' => 'this is list of my youtube video its video about white noise for baby make me titel to get most views based on youtube algrtiom The title must be under 100 characters, fully optimized for YouTube SEO, and written in clear, fluent English 
            Use emotionally driven language (e.g., soothe, calm, peaceful, magic sound, fall asleep fast), and mirror the successful rhythm and phrasing of the examples above. You may use pipes | or dashes - to separate parts naturally, but avoid emojis, clickbait, or artificial language.
            Return only the title — no commentary or explanation. Return only the final title — no extra explanation.
            1-White Noise for Babies - Soothe Your Fussy Baby to Sleep Fast with 10 Hours of Relaxing Sounds
2-White Noise for Babies | Comfort Your Crying Newborn with This 10-Hour Calming Sleep Sound
3-Colicky Baby Sleeps Soundly Tonight | 10 Hours White Noise for Babies | No Ads Peaceful Sound
4-Crying Infant Sleeps with White Noise | Magic Sound for Babies | Colicky Baby Sleep Solution
5-White Noise for Babies | Colicky Baby Sleeps Like Magic | 10 Hours of Peaceful Sleeping Sound
6-Colicky Baby Sleeps with Soothing Magic | White Noise for Babies | 10 Hours Without Disruption
7-Magic Sound for Colicky Baby | White Noise for Babies to Help Infant Sleep Through the Night
8-Baby White Noise Fan Sounds - White Noise Sound to Help You Fall Asleep
9-White Noise for Babies - 10 Hours of Gentle Sound to Calm Colicky Baby & Help Them Sleep
10-White Noise for Babies - Magic Sound for Baby Sleep - White Noise 10 Hours
11-White Noise - Transform Your Babys Sleep with White Noise for Babies
12-White Noise for Babies | 10 Hours of Soothing Sound to Help Colicky Baby Sleep Instantly
13-10 Hours of White Noise for Baby Sleep | Calm Crying Infants and Ensure a Peaceful Night
14-Colicky Baby Sleeps To This Magic Sound 🍼 White Noise for Babies 10 Hours ❤️ Soothe crying infant
15-Put a Baby to Sleep the Whole Night  🌙 10 Hours Magic Sounds to Help Soothe Colicky Infants Sleep
16-White Noise for Baby Sleep 👶 | Instantly Calm Crying & Sleep Soundly ✨
17-Colicky Baby Sleeps To This Magic Sound | White Noise 10 Hours | Soothe crying infant
18-White Noise For Babies - 10 Hours Magic Sounds to Help Soothe Colicky Infants and Better Sleep
19-White Noise for Babies - Magic Sound for Baby Sleep - White Noise 10 Hours
20-Colicky Baby Sleeps To This Magic Sound | White Noise 10 Hours | Soothe crying infant
21-White Noise for Crying Infant | 10 Hours of Magic Sound to Help Colicky Baby Sleep
22-White Noise for Babies | Colicky Baby Sleeps to This Magic Sound | 10 Hours Soothing Baby Sleep Aid
23-White Noise For Babies - 10 Hours of Gentle Sounds to Help Fussy Infants Sleep Better
24-10 Hours of White Noise for Infants | Nonstop Gentle Sound for a Comfortable and Restful Sleep
25-White Noise to Instantly Calm Crying Babies | Sleep Aid for Colicky Infants
26-White Noise for Babies - Soothe Your Fussy Baby to Sleep Fast with 10 Hours of Relaxing Sounds
27-White Noise for Babies - 10 Hours of Peaceful Sound to Help Colicky Infants Fall Asleep Fast
28-White Noise for Babies to Sleep Instantly | 10 Hours of Relaxing Sounds for Crying Infants
29-White Noise For Babies | Calming Sleep Aid to Soothe Colicky Infants and Relieve Irritability
30-Fussy Infant Falls Asleep to White Noise for Babies | 10 Hours of Relaxing and Peaceful Sound
31-White Noise Baby Sleep Aid | 10 Hours of Relaxing Sound to Comfort Fussy Newborns & Help Them Rest
32-White Noise For Babies | Perfect Sleep Solution for Fussy Newborns and Restless Nights
            ',
//             'Write one unique YouTube video title for a white noise video designed to help babies sleep. Use the same tone, structure, and emotional appeal as high-performing titles like:

// • Colicky Baby Sleeps To This Magic Sound | White Noise 10 Hours | Soothe Crying Infant
// • White Noise for Babies – 10 Hours of Peaceful Sound to Help Colicky Infants Fall Asleep Fast
// • White Noise for Crying Infant | 10 Hours of Magic Sound to Help Colicky Baby Sleep

// The title must be under 100 characters, fully optimized for YouTube SEO, and written in clear, fluent English.

// Focus on key themes such as: white noise, colic relief, crying infants, instant sleep, gentle/soothing sounds, and 10 hours of uninterrupted calming audio.

// Use emotionally driven language (e.g., soothe, calm, peaceful, magic sound, fall asleep fast), and mirror the successful rhythm and phrasing of the examples above. You may use pipes | or dashes - to separate parts naturally, but avoid emojis, clickbait, or artificial language.

// Return only the title — no commentary or explanation. Return only the final title — no extra explanation.',
        ]);
    
        return $response->json()["output"][0]['content'][0]['text'];
    }
}