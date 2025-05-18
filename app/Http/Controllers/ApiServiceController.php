<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use HlrLookup\HLRLookupClient;
use Illuminate\Support\Facades\Log;

class ApiServiceController extends Controller
{
    private function sanitizePhoneNumber($number)
    {
        return preg_replace('/\D/', '', $number);
    }
    public function getTelData($number)
    {
        // Sanitize the phone number
        $fullPhoneNumber = preg_replace('/\D/', '', $number);

        // Remove country code (assuming it's a fixed length like 91 for India)
        $localPhoneNumber = preg_replace('/^91/', '', $fullPhoneNumber);
        // Initialize response data
        $data = [
            'whatsappData' => null,
            'hlrData' => null,
            // 'eyeconData' => null,
            'truecallerData' => null,
            'allMobileData' => null,
            'socialMediaData' => null,
            'skypeData' => null,  // New Skype API Data
            'osintData' => null,
            // 'surepassKyc' => null, // Surepass KYC API
            // 'surepassUpi' => null, // Surepass UPI API
            // 'surepassBank' => null, // Surepass Bank API
            'errors' => [],
        ];
        // 🔹 Fetch SkypeSearch Data
        try {
            $skypeResponse = Http::post('http://127.0.0.1:8080/search/skype/', [
                'query' => $localPhoneNumber
            ]);

            if ($skypeResponse->successful()) {
                $data['skypeData'] = $skypeResponse->json();
            } else {
                $data['errors']['skype'] = "Skype API Error: HTTP Status {$skypeResponse->status()}";
            }
        } catch (\Exception $e) {
            $data['errors']['skype'] = "Skype API Exception: " . $e->getMessage();
        }
        // Fetch Osint data
        try {
            $osintResponse = Http::withHeaders([
                'x-api-key' => env('X_API_KEY')
            ])->get('http://127.0.0.1:5000/api/search/', [
                        'phone' => $fullPhoneNumber,
                        'per_page' => 50
                    ]);

            if ($osintResponse->successful()) {
                $data['osintData'] = $osintResponse->json();
            } else {
                $data['errors'][] = "Osint API Error: HTTP Status {$osintResponse->status()}";
            }
        } catch (\Exception $e) {
            $data['errors'][] = "Osint API Exception: " . $e->getMessage();
        }

        // Fetch WhatsApp data
        try {
            $whatsappResponse = Http::withHeaders([
                'x-rapidapi-key' => env('TEL_API_KEY'),
                'x-rapidapi-host' => env('TEL_API_HOST'),
            ])->get("https://whatsapp-data1.p.rapidapi.com/number/{$fullPhoneNumber}");

            if ($whatsappResponse->successful()) {
                $data['whatsappData'] = $whatsappResponse->json();
            } else {
                $data['errors']['whatsapp'] = $whatsappResponse->status();
            }
        } catch (\Exception $e) {
            $data['errors']['whatsapp'] = $e->getMessage();
        }

        // // Surepass KYC API (prefill-by-mobile)
        // try {
        //     $surepassKycResponse = Http::withHeaders([
        //         'Content-Type' => 'application/json',
        //         'Authorization' => 'Bearer ' . env('SUREPASS_KYC_TOKEN'),
        //     ])->post('https://kyc-api.surepass.io/api/v1/prefill/prefill-by-mobile', [
        //                 'mobile' => $localPhoneNumber,
        //             ]);

        //     if ($surepassKycResponse->successful()) {
        //         $data['surepassKyc'] = $surepassKycResponse->json();
        //     } else {
        //         $data['errors']['surepassKyc'] = "Surepass KYC API Error: HTTP Status {$surepassKycResponse->status()}";
        //     }
        // } catch (\Exception $e) {
        //     $data['errors']['surepassKyc'] = "Surepass KYC API Exception: " . $e->getMessage();
        // }
        // // Surepass UPI API (mobile-to-multiple-upi)
        // try {
        //     $surepassUpiResponse = Http::withHeaders([
        //         'Content-Type' => 'application/json',
        //         'Authorization' => 'Bearer ' . env('SUREPASS_KYC_TOKEN'),
        //     ])->post('https://kyc-api.surepass.io/api/v1/bank-verification/mobile-to-multiple-upi', [
        //                 'mobile_number' => $localPhoneNumber,
        //             ]);

        //     if ($surepassUpiResponse->successful()) {
        //         $data['surepassUpi'] = $surepassUpiResponse->json();
        //     } else {
        //         $data['errors']['surepassUpi'] = "Surepass UPI API Error: HTTP Status {$surepassUpiResponse->status()}";
        //     }
        // } catch (\Exception $e) {
        //     $data['errors']['surepassUpi'] = "Surepass UPI API Exception: " . $e->getMessage();
        // }
        // // Surepass Mobile to Bank Details API
        // try {
        //     $surepassBankResponse = Http::withHeaders([
        //         'Content-Type' => 'application/json',
        //         'Authorization' => 'Bearer ' . env('SUREPASS_KYC_TOKEN'),
        //     ])->post('https://kyc-api.surepass.io/api/v1/mobile-to-bank-details/verification', [
        //                 'mobile_no' => $localPhoneNumber,
        //             ]);

        //     if ($surepassBankResponse->successful()) {
        //         $data['surepassBank'] = $surepassBankResponse->json();
        //     } else {
        //         $data['errors']['surepassBank'] = "Surepass Bank API Error: HTTP Status {$surepassBankResponse->status()}";
        //     }
        // } catch (\Exception $e) {
        //     $data['errors']['surepassBank'] = "Surepass Bank API Exception: " . $e->getMessage();
        // }

        // Fetch HLR data using the SDK
        try {
            $hlrClient = new HLRLookupClient(
                env('HLR_API_KEY'),
                env('HLR_API_SECRET'),
                storage_path('logs/hlr-lookups.log') // Optional log file
            );

            $hlrResponse = $hlrClient->post('/hlr-lookup', [
                'msisdn' => $fullPhoneNumber,
            ]);

            if ($hlrResponse->httpStatusCode === 200) {
                $data['hlrData'] = $hlrResponse->responseBody;
            } else {
                $data['errors']['hlr'] = "HTTP Status: " . $hlrResponse->httpStatusCode;
            }
        } catch (\Exception $e) {
            $data['errors']['hlr'] = "Exception: " . $e->getMessage();
        }

        // Fetch Truecaller data
        try {
            $truecallerResponse = Http::withHeaders([
                'x-rapidapi-key' => env('TRUECALLER_API_KEY'),
                'x-rapidapi-host' => env('TRUECALLER_API_HOST'),
            ])->get("https://truecaller-data2.p.rapidapi.com/search/{$fullPhoneNumber}");

            if ($truecallerResponse->successful()) {
                $data['truecallerData'] = $truecallerResponse->json();
            } else {
                $data['errors']['truecaller'] = $truecallerResponse->status();
            }
        } catch (\Exception $e) {
            $data['errors']['truecaller'] = $e->getMessage();
        }

        // Fetch all Mobile Data (Callerapi,eyecon,truecaller,viewcaller etc)
        try {
            // Initialize the HLR Lookup Client
            $allMobileDataResponse = Http::withHeaders(headers: [
                'x-rapidapi-host' => env('ALL_MOBILE_API_HOST'),
                'x-rapidapi-key' => env('ALL_MOBILE_API_KEY'),
            ])->get(url: "https://caller-id-api1.p.rapidapi.com/api/phone/info/{$fullPhoneNumber}");
            if ($allMobileDataResponse->successful()) {
                $data['allMobileData'] = $allMobileDataResponse->json();
            } else {
                $data['errors']['allMobile'] = "All Mobile  Data API Error: HTTP Status {$allMobileDataResponse->status()}";
            }
        } catch (\Exception $e) {
            $data['errors']['allMobile'] = "All Mobile Data API Exception: " . $e->getMessage();
        }
        // Fetch social media data
        try {
            $socialMediaResponse = Http::withHeaders([
                'x-rapidapi-key' => env('SOCIAL_MEDIA_API_KEY'),
                'x-rapidapi-host' => env('SOCIAL_MEDIA_API_HOST'),
            ])->get("https://caller-id-social-search-eyecon.p.rapidapi.com/?phone={$fullPhoneNumber}");
            if ($socialMediaResponse->successful()) {
                $data['socialMediaData'] = $socialMediaResponse->json();
            } else {
                $data['errors']['socialMedia'] = "Social Media Data API Error: HTTP Status {$socialMediaResponse->status()}";
            }
        } catch (\Exception $e) {
            $data['errors']['socialMedia'] = "Social Media Data API Exception: " . $e->getMessage();
        }

        return response()->json($data);
    }


    public function searchSkype(Request $request)
    {
        // Validate request
        $request->validate([
            'query' => 'required|string',
        ]);

        // FastAPI URL
        $fastApiUrl = "http://127.0.0.1:8080/search/";

        // Send request to FastAPI
        $response = Http::post($fastApiUrl, [
            'query' => $request->query
        ]);

        // Check for errors
        if ($response->failed()) {
            return response()->json(['error' => 'Failed to fetch data from FastAPI'], 500);
        }

        // Return the response from FastAPI
        return response()->json($response->json());
    }
    public function fetchHibpData($email)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['error' => 'Invalid email address'], 400);
        }

        try {
            $client = new Client(['timeout' => 10]);
            $response = $client->request('GET', "https://haveibeenpwned.com/api/v3/breachedaccount/{$email}", [
                'headers' => [
                    'hibp-api-key' => env('HIBP_API_KEY'),
                    'User-Agent' => 'LaravelApp/1.0'
                ]
            ]);
            return response()->json(json_decode($response->getBody(), true));
        } catch (RequestException $e) {
            Log::error("HIBP API failed: " . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch data'], 500);
        }
    }

    public function getEmailData($email)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['error' => 'Invalid email address'], 400);
        }

        $responses = [
            'emailData' => null,
            'hibpData' => null,
            'skypeData' => null,  // New Skype API Data
            'zehefData' => null,
            'osintData' => null,
            'errors' => []
        ];
        // 🔹 Fetch SkypeSearch Data
        try {
            $skypeResponse = Http::post('http://127.0.0.1:8080/search/skype/', [
                'query' => $email
            ]);

            if ($skypeResponse->successful()) {
                $responses['skypeData'] = $skypeResponse->json();
            } else {
                $responses['errors'][] = "Skype API Error: HTTP Status {$skypeResponse->status()}";
            }
        } catch (\Exception $e) {
            $responses['errors'][] = "Skype API Exception: " . $e->getMessage();
        }

        // 🔹 Fetch Osint Data
        try {
            $osintResponse = Http::withHeaders([
                'x-api-key' => env('X_API_KEY'),  // Add this key in your .env
            ])->timeout(10)->get("http://127.0.0.1:5000/api/search/", [
                        'email' => $email,
                        'per_page' => 50
                    ]);

            if ($osintResponse->successful()) {
                $responses['osintData'] = $osintResponse->json();
            } else {
                $responses['errors'][] = "Custom Search API Error: HTTP Status {$osintResponse->status()}";
            }
        } catch (\Exception $e) {
            $responses['errors'][] = "Custom Search API Exception: " . $e->getMessage();
        }

        // 🔹 Fetch zehef Data
        try {
            $zehefResponse = Http::post('http://127.0.0.1:8080/search/zehef/', [
                'query' => $email
            ]);

            if ($zehefResponse->successful()) {
                $responses['zehefData'] = $zehefResponse->json();
            } else {
                $responses['errors'][] = "Skype API Error: HTTP Status {$zehefResponse->status()}";
            }
        } catch (\Exception $e) {
            $responses['errors'][] = "Zehef API Exception: " . $e->getMessage();
        }

        // 🔹 Fetch Google Email Data
        try {
            $googleResponse = Http::withHeaders([
                'x-rapidapi-key' => env('EMAIL_API_KEY'),
                'x-rapidapi-host' => env('EMAIL_API_HOST'),
            ])->timeout(10)->get("https://google-data.p.rapidapi.com/email/{$email}");

            if ($googleResponse->successful()) {
                $responses['emailData'] = $googleResponse->json();
            } else {
                $responses['errors'][] = 'Google API failed';
            }
        } catch (\Exception $e) {
            Log::error("Google API failed: " . $e->getMessage());
            $responses['errors'][] = 'Google API error';
        }

        // 🔹 Fetch HIBP Data
        try {
            $hibpResponse = Http::withHeaders([
                'hibp-api-key' => env('HIBP_API_KEY'),
                'User-Agent' => 'LaravelApp/1.0',
            ])->timeout(10)->get("https://haveibeenpwned.com/api/v3/breachedaccount/{$email}?truncateResponse=false");

            if ($hibpResponse->successful()) {
                $responses['hibpData'] = $hibpResponse->json();
            } else {
                $responses['errors'][] = 'HIBP API failed';
            }
        } catch (\Exception $e) {
            Log::error("HIBP API failed: " . $e->getMessage());
            $responses['errors'][] = 'HIBP API error';
        }

        return response()->json($responses);
    }

}