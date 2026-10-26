<?php

namespace App\Filament\Support;

trait RefreshesWorkflowRecords
{
    public function callMountedTableAction(array $arguments = []): mixed
    {
        $result = parent::callMountedTableAction($arguments);
        // Services may update a separately loaded model; show the committed state immediately.
        $this->flushCachedTableRecords();

        return $result;
    }
}
