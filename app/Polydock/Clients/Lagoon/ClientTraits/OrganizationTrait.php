<?php

namespace App\Polydock\Clients\Lagoon\ClientTraits;

use App\Polydock\Clients\Lagoon\LagoonClientInitializeRequiredToInteractException;

trait OrganizationTrait
{
    /**
     * @return array<string, mixed>
     *
     * @throws LagoonClientInitializeRequiredToInteractException
     */
    public function removeUserFromOrganizationGroups(int $orgId, string $userEmail): array
    {
        if (empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException;
        }

        $mutation = <<<'GQL'
            mutation ($orgId: Int!, $userEmail: String!) {
                removeUserFromOrganizationGroups(input: {
                    organization: $orgId,
                    user: {
                        email: $userEmail
                    }
                }){
                    id
                    name
                }
            }
        GQL;

        $response = $this->graphqlClient->query($mutation, [
            'orgId' => $orgId,
            'userEmail' => $userEmail,
        ]);

        if ($response->hasErrors()) {
            return ['error' => $response->getErrors()];
        }

        // Returns an array with all the data returned by the GraphQL server.
        return $response->getData();
    }

    /**
     * Attach a Lagoon organization deploy key to a project, so builds pull git with it.
     *
     * @return array<string, mixed>
     *
     * @throws LagoonClientInitializeRequiredToInteractException
     */
    public function addOrganizationKeyToProject(int $keyId, string $projectName): array
    {
        if (in_array($this->lagoonToken, [null, ''], true) || $this->graphqlClient === null) {
            throw new LagoonClientInitializeRequiredToInteractException;
        }

        $mutation = <<<'GQL'
            mutation ($id: Int!, $project: String!) {
                addOrganizationKeyToProject(id: $id, project: $project) {
                    id
                    name
                }
            }
        GQL;

        $response = $this->graphqlClient->query($mutation, [
            'id' => $keyId,
            'project' => $projectName,
        ]);

        if ($response->hasErrors()) {
            return ['error' => $response->getErrors()];
        }

        return $response->getData();
    }
}
