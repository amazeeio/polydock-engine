<?php

namespace App\Polydock\Clients\Lagoon\ClientTraits;

use App\Polydock\Clients\Lagoon\LagoonClientInitializeRequiredToInteractException;

trait GroupTrait
{
    /**
     * Get all groups
     *
     * @throws LagoonClientInitializeRequiredToInteractException if client not initialized
     */
    public function getAllGroups(): array
    {
        if (empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException;
        }

        $query = <<<'GQL'
            query q {
                allGroups {
                    id
                    name
                    type
                    organization
                }
            }
        GQL;

        $response = $this->graphqlClient->query($query);

        if ($response->hasErrors()) {
            return ['error' => $response->getErrors()];
        }

        $data = $response->getData();

        return $data['allGroups'];
    }

    /**
     * @throws LagoonClientInitializeRequiredToInteractException
     */
    public function addGroupToProject(string $groupName, string $projectName): array
    {
        if (empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException;
        }

        $mutation = <<<'GQL'
            mutation ($projectName: String!, $groupName: String!) {
                addGroupsToProject (
                    input: {
                        project: {
                            name: $projectName
                        }
                        groups: {
                            name: $groupName
                        }
                    }
                ) {
                    id
                }
            }
        GQL;

        $response = $this->graphqlClient->query($mutation, [
            'projectName' => $projectName,
            'groupName' => $groupName,
        ]);

        if ($response->hasErrors()) {
            return ['error' => $response->getErrors()];
        } else {
            // Returns an array with all the data returned by the GraphQL server.
            return $response->getData();
        }

    }

    /**
     * @throws LagoonClientInitializeRequiredToInteractException
     */
    public function removeUserFromGroup(string $groupName, string $userEmail): array
    {
        if (empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException;
        }

        $mutation = <<<'GQL'
            mutation ($groupName: String!, $userEmail: String!) {
                removeUserFromGroup(input: {
                    group: {
                        name: $groupName
                    },
                    user: {
                        email: $userEmail
                    }
                }){
                    id
                    name
                    memberCount
                }
            }
        GQL;

        $response = $this->graphqlClient->query($mutation, [
            'groupName' => $groupName,
            'userEmail' => $userEmail,
        ]);

        if ($response->hasErrors()) {
            return ['error' => $response->getErrors()];
        } else {
            // Returns an array with all the data returned by the GraphQL server.
            return $response->getData();
        }

    }

    /**
     * Gets users for a group
     *
     * @param  string  $groupName  The name of the group
     * @return array Emails of users in group
     *
     * @throws LagoonClientInitializeRequiredToInteractException if client not initialized
     */
    public function getGroupUsers(string $groupName): array
    {
        if (empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException;
        }

        $query = <<<'GQL'
            query ($groupName: String!) {
                groupByName(name: $groupName) {
                    members {
                        user {
                            email
                        }
                    }
                }
            }
        GQL;

        $response = $this->graphqlClient->query($query, [
            'groupName' => $groupName,
        ]);

        /***
         * Example Response
         * {
  "data": {
    "groupByName": {
      "members": [
        {
          "user": {
            "email": "default-user@polydock-amber-tiger-6994a164bf781"
          }
        },
        {
          "user": {
            "email": "hello@bryangruneberg.com"
          }
        },
        {
          "user": {
            "email": "hello@bryangruneberg.com"
          }
        }
      ]
    }
  }
}
         */

        if ($response->hasErrors()) {
            return ['error' => $response->getErrors()];
        }

        $data = $response->getData();

        $emails = [];
        foreach ($data['groupByName']['members'] ?? [] as $member) {
            if (isset($member['user']['email'])) {
                $emails[] = $member['user']['email'];
            }
        }

        return $emails;
    }
}
