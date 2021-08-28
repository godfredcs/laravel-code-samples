<?php
namespace App\Services;

use Exception;
use App\Models\LocalAuthority;

class LocalAuthorityService extends Service
{
    /**
     * Specifies the model
     *
     * @var \App\Modules\Projects\Models\LocalAuthority
     */
    protected $model = LocalAuthority::class;

    /**
     * Show the resource with all its relations
     *
     * @var bool
     */
    protected $showWithRelations = true;

    /**
     * Toggles the is_hidden property of a given local authority
     *
     * @param int $id
     * @return void
     */
    public function toggleIsHidden(LocalAuthority $local_authority): LocalAuthority
    {
        $resource->is_hidden = !$resource->is_hidden;
        $resource->save();

        return $resource->refresh();
    }
}
