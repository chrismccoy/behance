<?php

class BehanceProjectFetcher {
    private string $url;
    private array $headers;
    private array $cookies;

    public function __construct() {
        $this->url = "https://www.behance.net/v3/graphql";
        $this->headers = [
            "X-Requested-With" => "XMLHttpRequest",
            "X-BCP" => "96ee8700-3ce5-4445-96b2-ab0e1a76a63a",
	        "Content-Type" => "application/json",
        ];
	    $this->cookies = [
		    'bcp' => '96ee8700-3ce5-4445-96b2-ab0e1a76a63a',
	    ];
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
                        __typename
                        adminFlags {
                            mature_lock
                            privacy_lock
                            dmca_lock
                            flagged_lock
                            privacy_violation_lock
                            trademark_lock
                            spam_lock
                            eu_ip_lock
                        }
                        colors {
                            r
                            g
                            b
                        }
                        covers {
                            size_202 { url }
                            size_404 { url }
                            size_808 { url }
                            size_original { url }
                        }
                        features {
                            url
                            name
                            featuredOn
                            ribbon {
                                image
                                image2x
                                image3x
                            }
                        }
                        fields {
                            id
                            label
                            slug
                            url
                        }
                        hasMatureContent
                        id
                        isFeatured
                        isHiddenFromWorkTab
                        isMatureReviewSubmitted
                        isOwner
                        isFounder
                        isPinnedToSubscriptionOverview
                        isPrivate
                        linkedAssets { ...sourceLinkFields }
                        linkedAssetsCount
                        sourceFiles { ...sourceFileFields }
                        matureAccess
                        modifiedOn
                        name
                        owners {
                            ...OwnerFields
                            images { size_50 { url } }
                        }
                        premium
                        publishedOn
                        stats {
                            appreciations { all }
                            views { all }
                            comments { all }
                        }
                        slug
                        tools {
                            id
                            title
                            category
                            categoryLabel
                            categoryId
                            approved
                            url
                            backgroundColor
                        }
                        url
                    }
                }
            }
        }

        fragment sourceFileFields on SourceFile {
            __typename
            sourceFileId
            projectId
            userId
            title
            assetId
            renditionUrl
            mimeType
            size
            category
            licenseType
            unitAmount
            currency
            tier
            hidden
            extension
            hasUserPurchased
        }

        fragment sourceLinkFields on LinkedAsset {
            __typename
            name
            premium
            url
            category
            licenseType
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

    public function fetchProjectData(string $username, int $pages = 60): array {
        $projects = [];
        for ($page = 0; $page < $pages; $page++) {
            $afterValue = base64_encode((string)($page * 12));
            $payload = $this->createPayload($username, $afterValue);

            try {
                $response = wp_remote_post($this->url, [
                    'headers' => $this->headers,
		            'cookies' => $this->cookies,
                    'body' => json_encode($payload),
                ]);

                if (is_wp_error($response)) {
                    throw new Exception($response->get_error_message());
                }

                $projects = array_merge($projects, $this->processResponse($response));
            } catch (\Exception $e) {
                echo "Error fetching data: " . $e->getMessage();
            }
        }
        return $projects;
    }

    private function processResponse($response): array {
        $projects = [];
        $data = json_decode(wp_remote_retrieve_body($response), true);
        $nodes = $data['data']['user']['profileProjects']['nodes'] ?? [];
        $projects = array_map([$this, 'displayProjectArray'], $nodes);
        return $projects;
    }

    private function displayProjectArray(array $project): array {
        return [
            'name' => $project['name'],
            'url' => $project['url'],
            'image' => $project['covers']['size_original']['url']
        ];
    }
}

$fetcher = new BehanceProjectFetcher();
echo json_encode($fetcher->fetchProjectData('pugbomb', 1));
