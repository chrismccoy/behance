<?php

require "vendor/autoload.php";

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;

class BehanceMoodboardFetcher {
    private Client $client;
    private string $url;
    private array $headers;
    private CookieJar $cookies;

    public function __construct() {
        $this->url = "https://www.behance.net/v3/graphql";
        $this->headers = [
            "X-Requested-With" => "XMLHttpRequest",
            "X-BCP" => "96ee8700-3ce5-4445-96b2-ab0e1a76a63a",
        ];
        $this->cookies = CookieJar::fromArray(['bcp' => '96ee8700-3ce5-4445-96b2-ab0e1a76a63a'], 'behance.net');
        $this->client = new Client();
    }

    private function createPayload(int $id, string $afterValue): array {
        return [
            "query" => $this->getGraphQLQuery(),
            "variables" => [
                "afterItem" => $afterValue,
                "firstItem" => 40,
                "shouldGetItems" => true,
                "shouldGetMoodboardFields" => false,
                "shouldGetRecommendations" => false,
                "id" => $id,
            ]
        ];
    }

    private function getGraphQLQuery(): string {
        return <<<GQL
        query GetMoodboardItemsAndRecommendations(
                \$id: Int!
                \$firstItem: Int!
                \$afterItem: String
                \$shouldGetRecommendations: Boolean!
                \$shouldGetItems: Boolean!
                \$shouldGetMoodboardFields: Boolean!
        ) {
            viewer @include(if: \$shouldGetMoodboardFields) {
                isOptedOutOfRecommendations
                isAdmin
            }
            moodboard(id: \$id) {
                items(first: \$firstItem, after: \$afterItem) @include(if: \$shouldGetItems) {
                    pageInfo {
                        endCursor
                        hasNextPage
                    }
                    nodes {
                        ...nodesFields
                    }
                }

                recommendedItems(first: 80) @include(if: \$shouldGetRecommendations) {
                    nodes {
                        ...nodesFields
                        fetchSource
                    }
                }
            }
        }

        fragment projectFields on Project {
            __typename
            id
            publishedOn
            name
            url
            fields { label }
            covers {
                size_original { url }
                size_404 { url }
            }
            stats {
                views { all }
                appreciations { all }
                comments { all }
            }
        }

        fragment nodesFields on MoodboardItem {
            entity {
                ... on Project { ...projectFields }
            }
        }

        GQL;
    }

    public function fetchMoodboardData(int $id, int $pages = 1): array {
        $projects = [];
        for ($page = 0; $page < $pages; $page++) {
            $afterValue = base64_encode((string)($page * 12));
            $payload = $this->createPayload($id, $afterValue);

            try {
                $response = $this->client->post($this->url, [
                    'headers' => $this->headers,
                    'cookies' => $this->cookies,
                    'json' => $payload
                ]);
                $projects = array_merge($projects, $this->processResponse($response));
                //$projects = $this->processResponse($response);
            } catch (\Exception $e) {
                echo "Error fetching data: " . $e->getMessage();
            }
        }
        return $projects;
    }

    private function processResponse($response): array {
        $data = json_decode($response->getBody(), true);
        $nodes = $data['data']['moodboard']['items']['nodes'] ?? [];
        return array_map([$this, 'displayMoodboardArray'], $nodes);
    }

    private function displayMoodboardArray(array $project): array {
        return [
            'id' => $project['entity']['id'],
            'name' => $project['entity']['name'],
            'url' => $project['entity']['url'],
            'publishedOn' => $project['entity']['publishedOn'],
            'image' => $project['entity']['covers']['size_original']['url'],
            'thumbnail' => $project['entity']['covers']['size_404']['url'],
            //'stats' => $project['entity']['stats'],
            'views' => $project['entity']['stats']['views']['all'],
            'apprecations' => $project['entity']['stats']['appreciations']['all'],
            'comments' => $project['entity']['stats']['comments']['all'],
        ];
    }
}

$fetcher = new BehanceMoodboardFetcher();
echo json_encode($fetcher->fetchMoodboardData(217027557));
