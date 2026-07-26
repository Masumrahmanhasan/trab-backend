<?php

namespace App\Models\Concerns;

trait HasTeamContext
{
    protected ?int $teamId = null;

    public function setTeamId(?int $teamId): static
    {
        $this->teamId = $teamId;
        return $this;
    }

    public function getTeamId(): ?int
    {
        return $this->teamId;
    }

    public function clearTeamId(): static
    {
        $this->teamId = null;
        return $this;
    }
}
