<?php

namespace Ang3\Component\Odoo\ORM\Schema;

class Selection
{
    /**
     * @var array<Choice>
     */
    private $choices = [];

    public function getIds(): array
    {
        $ids = [];

        foreach ($this->choices as $choice) {
            /* @var Choice $choice */
            $ids[] = $choice->getId();
        }

        return $ids;
    }

    public function getNames(): array
    {
        $names = [];

        foreach ($this->choices as $choice) {
            /* @var Choice $choice */
            $names[] = $choice->getName();
        }

        return $names;
    }

    public function getValues(): array
    {
        $values = [];

        foreach ($this->choices as $choice) {
            /* @var Choice $choice */
            $values[] = $choice->getValue();
        }

        return $values;
    }

    public function getChoices(): array
    {
        return $this->choices;
    }
}
