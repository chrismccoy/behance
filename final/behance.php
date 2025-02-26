<?php

defined( 'ABSPATH' ) || exit;

class Behance {

    protected string $url = 'https://www.behance.net/v3/graphql';
    protected array $headers;
    protected array $cookies;

    public function __construct() {
		$this->headers = [
			"X-Requested-With" => "XMLHttpRequest",
			"X-BCP"            => "96ee8700-3ce5-4445-96b2-ab0e1a76a63a",
			"Content-Type"     => "application/json",
		];
		$this->cookies = [
			'bcp' => '96ee8700-3ce5-4445-96b2-ab0e1a76a63a',
		];
    }

    // Make an API request to GraphQL endpoint
    private function request_graphql( $endpoint, $variables ) {

        $data = array(
            'query' => $this->get_graphql_query( $endpoint ),
            'variables' => $variables,
        );

        $response = wp_remote_post( $this->url, [
			'headers' => $this->headers,
			'cookies' => $this->cookies,
			'body'    => json_encode( $data ),
		]);

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        return isset( $data['data'] ) ? $data['data'] : null;
    }

    private function get_query_profile_projects() {
		return <<<GQL
			query GetProfileProjects(\$username: String, \$after: String, \$first: Int) {
				user(username: \$username) {
					profileProjects(first: \$first, after: \$after) {
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
								size_404 {
									url
								}
								size_808 {
									url
								}
								size_original {
									url
								}
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
							linkedAssets {
								...sourceLinkFields
							}
							linkedAssetsCount
							sourceFiles {
								...sourceFileFields
							}
							matureAccess
							modifiedOn
							name
							owners {
								...OwnerFields
								images {
									size_50 {
										url
									}
									size_115 {
										url
									}
								}
							}
							premium
							publishedOn
							stats {
								appreciations {
									all
								}
								views {
									all
								}
								comments {
									all
								}
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

    	private function get_graphql_query( $query_type ) {
		$queries = [];

		$queries['GetProfileProjects'] = $this->get_query_profile_projects();

        	return isset( $queries[$query_type] ) ? $queries[$query_type] : '';
    	}

	private function format_project_info( array $project, array &$data ): array {

		// Extract Fields
		if ( ! empty( $project['fields'] ) ) {
			$data['fields'] = array_column( $project['fields'], null, 'id' );
		}

		// Extract Owners
		if ( ! empty( $project['owners'] ) ) {
			foreach ( $project['owners'] as $owner ) {
				// Get the last image URL, if available
				$last_image = end( $owner['images'] ); // Get the last image from the array
				$image_url = $last_image['url'] ?? '';  // Use null coalescing operator to handle missing 'url'

				// Extract necessary values once
				$owner_data = [
					'id'               => $owner['id'],
					'displayName'      => $owner['displayName'],
					'username'         => $owner['username'],
					'url'              => $owner['url'],
					'location'         => $owner['location'],
					'locationUrl'      => $owner['locationUrl'],
					'hasPremiumAccess' => $owner['hasPremiumAccess'],
					'images'           => $image_url,
				];

				// Add to the 'owners' array with owner ID as key
				$data['owners'][$owner['id']] = $owner_data;
			}
		}

		$data['projects'][$project['id']] = [
			'id'           => $project['id'],
			'owner_ids'    => !empty( $project['owners'] ) ? array_column( $project['owners'], 'id' ) : [],
			'field_ids'    => !empty( $project['fields'] ) ? array_column( $project['fields'], 'id' ) : [],
			'slug'         => $project['slug'],
			'name'         => $project['name'],
			'url'          => $project['url'],
			'published_on' => $project['publishedOn'],
			'modified_on'  => $project['modifiedOn'],
			'thumbnail'    => $this->get_large_thumbnail( $project ),
			'views'        => $project['stats']['views']['all'] ?? 0,
			'comments'     => $project['stats']['comments']['all'] ?? 0,
			'likes'        => $project['stats']['appreciations']['all'] ?? 0
		];

		return $data;
	}

	public function get_large_thumbnail( $project ) {
		if ( empty( $project['covers'] ) ) return '';
		$covers = $project['covers'];
		$cover = $covers['size_808'] ?? $covers['size_404'] ?? $covers['size_202'] ?? null;
		return $cover ? $cover['url'] : '';
	}

	public function fetch_profile_projects( string $username, int $batch_size = 50, int $offset = 0 ) {

		$total_fetched = 0;
		$_data = [
			'owners'   => [],
			'fields'   => [],
			'projects' => [],
		];

		while ( $total_fetched < $batch_size ) {
			$remaining = $batch_size - $total_fetched; // Calculate how many more we need

			$variables = [
				'username' => $username,
				'first'    => $remaining, // Request only the remaining count
			];

			if ( $offset > 0 ) {
				$variables['after'] = base64_encode( (string) $offset ); // Encode offset
			}

			$data = $this->request_graphql( 'GetProfileProjects', $variables );

			$nodes = $data['user']['profileProjects']['nodes'] ?? [];

			if ( empty( $nodes ) ) {
				break; // No more data, stop fetching
			}

			foreach ( $nodes as $node ) {
				if ( $total_fetched >= $batch_size ) {
					break; // Stop if we reached the limit
				}
				$this->format_project_info( $node, $_data );
				$total_fetched++;
			}

			$offset += count( $nodes ); // Update the offset for the next batch
		}

		if ( empty( $_data['projects'] ) ) {
			return false;
		}

		return $_data;
	}

}
