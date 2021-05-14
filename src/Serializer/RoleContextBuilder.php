<?php

declare(strict_types=1);

namespace App\Serializer;

use ApiPlatform\Core\Serializer\SerializerContextBuilderInterface;
use App\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class RoleContextBuilder implements SerializerContextBuilderInterface
{
    protected array $context;
    protected bool $isAdmin = false;
    protected bool $isPM = false;

    protected $decorated;
    protected $authorizationChecker;

    public function __construct(SerializerContextBuilderInterface $decorated, AuthorizationCheckerInterface $authorizationChecker)
    {
        $this->decorated = $decorated;
        $this->authorizationChecker = $authorizationChecker;
    }

    /**
     * {@inheritdoc}
     */
    public function createFromRequest(Request $request, bool $normalization, array $extractedAttributes = null): array
    {
        $this->context = $this->decorated->createFromRequest($request, $normalization, $extractedAttributes);

        $resourceClass = $this->context['resource_class'] ?? null;
        if (!$resourceClass) {
            return $this->context;
        }

        // convert "App\Entity\Project" to "project"
        $objectType = lcfirst(
            substr(
                strrchr($resourceClass, '\\'),
                1
            )
        );

        $requestType = $normalization ? 'read' : 'write';
        $this->isAdmin = $this->authorizationChecker->isGranted(User::ROLE_ADMIN);
        $this->isPM = $this->authorizationChecker->isGranted(User::ROLE_PROCESS_MANAGER);

        // add "project:admin-write", "project:pm-read" etc for properties only
        // readable/writeable for those roles
        $this->addGroups($objectType, $requestType);

        // add "project:create" etc for properties only writeable on creation
        if ('collection' === $this->context['operation_type']
            && 'post' === $this->context['collection_operation_name']
        ) {
            $this->addGroups($objectType, 'create');
        }

        // add "project:update" etc for properties only writeable on update
        if ('item' === $this->context['operation_type']
            && 'put' === $this->context['item_operation_name']
        ) {
            $this->addGroups($objectType, 'update');
        }

        if (isset($this->context['collection_operation_name'])) {
            $this->addGroups($objectType, $this->context['collection_operation_name']);
        }

        if (isset($this->context['item_operation_name'])) {
            $this->addGroups($objectType, $this->context['item_operation_name']);

            // remove $objectType:write from the groups if a special operation
            // is used (like "submit" or "activate") to prevent setting of properties
            // via these custom actions
            if ('put' !== $this->context['item_operation_name']
                && 'delete' !== $this->context['item_operation_name']
            ) {
                foreach (array_keys($this->context['groups'], "$objectType:write", true) as $key) {
                    unset($this->context['groups'][$key]);
                }
            }
        }

        return $this->context;
    }

    protected function addGroups(string $object, string $action)
    {
        // group "$objectType:$requestType" (e.g. "project:write") is added
        // by the decorated builder, no need to do it here
        if ('write' !== $action && 'read' !== $action) {
            $this->addGroup($object, $action);
        }

        // add "project:admin-read" etc.
        if ($this->isAdmin) {
            $this->addGroup($object, $action, 'admin');
        }

        // add "project:pm-read" etc.
        if ($this->isPM) {
            $this->addGroup($object, $action, 'pm');
        }
    }

    protected function addGroup(string $object, string $action, ?string $user = null)
    {
        if ($user) {
            $this->context['groups'][] = sprintf(
                '%s:%s-%s', $object, $user, $action
            );
        } else {
            $this->context['groups'][] = sprintf(
                '%s:%s', $object, $action
            );
        }
    }
}
