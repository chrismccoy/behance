<?php

require "vendor/autoload.php";

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;

class BehanceProjectFetcher {
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

    private function createPayload(string $username, string $afterValue): array {
        return [
            "query" => $this->getGraphQLQuery(),
            "variables" => [
                "username" => $username,
                "after" => $afterValue
            ]
        ];
    }

    private function getGraphQLQuery(): string {
        return <<<GQL
        query GetProfileProjects(\$username: String, \$after: String) {
            user(username: \$username) {
                profileProjects(first: 12, after: \$after) {
                    pageInfo {
                        endCursor
                        hasNextPage
                    }
                    nodes {
                        covers {
                            size_202 { url }
                            size_404 { url }
                            size_808 { url }
                            size_original { url }
                        }
                        fields {
                            id
                            label
                            slug
                            url
                        }
                        id
                        name
                        publishedOn
                        stats {
                            appreciations { all }
                            views { all }
                            comments { all }
                        }
                        slug
                        url
                    }
                }
            }
        }

        GQL;
    }

    public function fetchProjectData(string $username, int $pages = 60): array {
        $projects = [];
        for ($page = 0; $page < $pages; $page++) {
            $afterValue = base64_encode((string)($page * 12));
            $payload = $this->createPayload($username, $afterValue);

            try {
                $response = $this->client->post($this->url, [
                    'headers' => $this->headers,
                    'cookies' => $this->cookies,
                    'json' => $payload
                ]);
                $projects = array_merge($projects, $this->processResponse($response));
            } catch (\Exception $e) {
                echo "Error fetching data: " . $e->getMessage();
            }
        }
        return $projects;
    }

    private function processResponse($response): array {
        $projects = [];
        $data = json_decode($response->getBody(), true);
        $nodes = $data['data']['user']['profileProjects']['nodes'] ?? [];
        $projects = array_map([$this, 'displayProjectArray'], $nodes);
        return $projects;
    }

    private function displayProjectArray(array $project): array {
        return [
            'id' => $project['id'],
            'slug' => $project['slug'],
            'name' => $project['name'],
            'url' => $project['url'],
            'created_on' => $project['publishedOn'],
            'thum_image' => $project['covers']['size_404']['url'],
            'big_img' => $project['covers']['size_original']['url'],
            'views' => $project['stats']['views']['all'],
            'comments' => $project['stats']['comments']['all'],
            'likes' => $project['stats']['appreciations']['all'],
            'fields' => $project['fields'],
            'field_id' => $project['fields']['id'],
        ];
    }
}

$fetcher = new BehanceProjectFetcher();
print_r($fetcher->fetchProjectData('pugbomb', 1));
