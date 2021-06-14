<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\Project;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class ProjectValidator
{
    /**
     * Called when a project is created.
     */
    public static function validateCreation(Project $object, ExecutionContextInterface $context, $payload)
    {
        if (!$object->getCouncil()) {
            // handled by the validator on the property
            return;
        }

        if (!$object->getCouncil()->isActive()) {
            $context->buildViolation('validate.project.council.notActive')
                ->atPath('council')
                ->addViolation()
            ;
        }
    }

    /**
     * Called when a project is updated.
     */
    public static function validateUpdate(Project $object, ExecutionContextInterface $context, $payload)
    {
        /*
         * @todo empty name is allowed, maybe prevent removing an existing
         * name so it cannot get unnamed again?
        if (empty($object->getName())) {
            $context->buildViolation('This value should not be blank.')
                ->atPath('name')
                ->addViolation();
        }
        */
    }
}
