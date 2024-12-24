<?php

class BehanceMoodboardFetcher {
    private $url;
    private $headers;
    private $cookies;

    public function __construct() {
        $this->url = "https://www.behance.net/v3/graphql";
        $this->headers = [
            "X-Requested-With" => "XMLHttpRequest",
            "X-BCP" => "96ee8700-3ce5-4445-96b2-ab0e1a76a63a",
	    "Content-Type" => "application/json",
        ];
        $this->cookies = ['bcp' => '96ee8700-3ce5-4445-96b2-ab0e1a76a63a'];
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
                ...moodboardFields @include(if: \$shouldGetMoodboardFields)

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

        fragment moodboardFields on Moodboard {
            id
            label
            privacy
            followerCount
            isFollowing
            projectCount
            url
            isOwner
            owners {
                ...OwnerFields
                images {
                    size_50 { url }
                    size_100 { url }
                    size_115 { url }
                    size_230 { url }
                    size_138 { url }
                    size_276 { url }
                }
            }
        }

        fragment projectFields on Project {
            __typename
            id
            isOwner
            publishedOn
            matureAccess
            hasMatureContent
            modifiedOn
            name
            url
            isPrivate
            slug
            license {
                license
                description
                id
                label
                url
                text
                images
            }
            fields { label }
            colors { r g b }
            owners {
                ...OwnerFields
                images {
                    size_50 { url }
                    size_100 { url }
                    size_115 { url }
                    size_230 { url }
                    size_138 { url }
                    size_276 { url }
                }
            }
            covers {
                size_original { url }
                size_max_808 { url }
                size_808 { url }
                size_404 { url }
                size_202 { url }
                size_230 { url }
                size_115 { url }
            }
            stats {
                views { all }
                appreciations { all }
                comments { all }
            }
        }

        fragment exifDataValueFields on exifDataValue {
            id
            label
            value
            searchValue
        }

        fragment nodesFields on MoodboardItem {
            id
            entityType
            width
            height
            flexWidth
            flexHeight
            images { size url }

            entity {
                ... on Project { ...projectFields }
                ... on ImageModule {
                    project { ...projectFields }
                    colors { r g b }
                    exifData {
                        lens { ...exifDataValueFields }
                        software { ...exifDataValueFields }
                        makeAndModel { ...exifDataValueFields }
                        focalLength { ...exifDataValueFields }
                        iso { ...exifDataValueFields }
                        location { ...exifDataValueFields }
                        flash { ...exifDataValueFields }
                        exposureMode { ...exifDataValueFields }
                        shutterSpeed { ...exifDataValueFields }
                        aperture { ...exifDataValueFields }
                    }
                }
                ... on MediaCollectionComponent { project { ...projectFields } }
            }
        }

        fragment OwnerFields on User {
            displayName
            hasPremiumAccess
            id
            isFollowing
            isProfileOwner
            location
            locationUrl
            url
            username
            availabilityInfo {
                availabilityTimeline
                isAvailableFullTime
                isAvailableFreelance
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
                $response = wp_remote_post($this->url, [
                    'headers' => $this->headers,
                    'cookies' => $this->cookies,
                    'body' => json_encode($payload),
                    'timeout' => 15,
                    'data_format' => 'body'
                ]);
                if (is_wp_error($response)) {
                    throw new Exception($response->get_error_message());
                }
                $projects = array_merge($projects, $this->processResponse($response));
                //$projects = $this->processResponse($response);
            } catch (Exception $e) {
                echo "Error fetching data: " . $e->getMessage();
            }
        }
        return $projects;
    }

    private function processResponse($response): array {
        $data = json_decode(wp_remote_retrieve_body($response), true);
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
