<?php

require "vendor/autoload.php";

use GuzzleHttp\Client;

class BehanceGalleryFetcher {
    private Client $client;
    private array $headers;

    public function __construct() {
        $this->client = new Client();
        $this->headers = [
            "X-Requested-With" => "XMLHttpRequest",
        ];
    }

    public function fetchGalleryData(string $gallery): array {
        try {
            $response = $this->client->get($gallery);
            return $this->processResponse($response);
        } catch (\Exception $e) {
            error_log("Error fetching data: " . $e->getMessage());
            return [];
        }
    }

    private function processResponse($response): array {
        $data = $response->getBody()->getContents();
        return $this->extractImageUrls($data);
    }

    private function extractImageUrls(string $data): array {
        $dom = new DOMDocument();
        @$dom->loadHTML($data);

        $imageUrls = [];
        foreach ($dom->getElementsByTagName('script') as $script) {
            if ($this->isValidScriptTag($script)) {
                $jsonData = $script->nodeValue;
                $parsedData = json_decode($jsonData, true);
                $imageUrls = array_merge($imageUrls, $this->getProjectImageUrls($parsedData));
            }
        }
        return $imageUrls;
    }

    private function isValidScriptTag($script): bool {
        return $script->getAttribute('type') === 'application/json' &&
               $script->getAttribute('id') === 'beconfig-store_state';
    }

    private function getProjectImageUrls(array $parsedData): array {
        $imageUrls = [];
        if (isset($parsedData['project']['project']['modules'])) {
            foreach ($parsedData['project']['project']['modules'] as $project) {
                if (isset($project['imageSizes']['allAvailable'][0]['url'])) {
                    $imageUrls[] = $project['imageSizes']['allAvailable'][0]['url'];
                }
            }
        }
        return $imageUrls;
    }
}

$fetcher = new BehanceGalleryFetcher();
print_r($fetcher->fetchGalleryData('https://www.behance.net/gallery/201916747/Turning-Red-Re-Creation'));
