<?php

/**
 * InventoryController.php
 *
 * -Description-
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * @link       https://www.librenms.org
 *
 * @copyright  2023 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace App\Http\Controllers\Table;

use App\Models\EntPhysical;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;

class InventoryController extends TableController
{
    protected $model = EntPhysical::class;

    public function rules()
    {
        return [
            'device' => 'nullable|int',
            'descr' => 'nullable|string',
            'model' => 'nullable|string',
            'serial' => 'nullable|string',
        ];
    }

    protected function filterFields($request)
    {
        return [
            'device_id' => 'device',
        ];
    }

    protected function searchFields($request)
    {
        return [
            'entPhysicalDescr',
            'entPhysicalModelName',
            'entPhysicalSerialNum',
            'entPhysicalMfgName',
            'entPhysicalClass',
            'entPhysicalHardwareRev',
            'entPhysicalFirmwareRev',
            'entPhysicalSoftwareRev'
        ];
    }

    protected function sortFields($request)
    {
        return [
            'device' => 'device_id',
            'mfg' => 'entPhysicalMfgName',
            'class' => 'entPhysicalClass',
            'name' => 'entPhysicalName',
            'descr' => 'entPhysicalDescr',
            'model' => 'entPhysicalModelName',
            'serial' => 'entPhysicalSerialNum',
            'hw_rev' => 'entPhysicalHardwareRev',
            'fw_rev' => 'entPhysicalFirmwareRev',
            'sw_rev' => 'entPhysicalSoftwareRev',
            'fru' => 'entPhysicalIsFRU',
        ];
    }

    protected function baseQuery($request)
    {
        $query = EntPhysical::hasAccess($request->user())
            ->with('device')
            ->select([
                'entPhysical_id',
                'device_id',
                'entPhysicalDescr',
                'entPhysicalName',
                'entPhysicalModelName',
                'entPhysicalSerialNum',
                'entPhysicalMfgName',
                'entPhysicalClass',
                'entPhysicalHardwareRev',
                'entPhysicalFirmwareRev',
                'entPhysicalSoftwareRev',
                'entPhysicalIsFRU'
            ]);

        // apply specific field filters
        $this->search($request->get('descr'), $query, ['entPhysicalDescr']);
        $this->search($request->get('model'), $query, ['entPhysicalModelName']);
        $this->search($request->get('serial'), $query, ['entPhysicalSerialNum']);

        return $query;
    }

    /**
     * @param  EntPhysical  $entPhysical
     * @return array|Model|Collection
     */
    public function formatItem($entPhysical)
    {
        $hwRev = $entPhysical->entPhysicalHardwareRev ?: ($entPhysical->device->hardware ?? 'N/A');
        $swRev = $entPhysical->entPhysicalSoftwareRev ?: ($entPhysical->device->version ?? 'N/A');
        $model = $entPhysical->entPhysicalModelName ?: ($entPhysical->device->hardware ?? 'N/A');
        $serial = $entPhysical->entPhysicalSerialNum ?: ($entPhysical->device->serial ?? 'N/A');

        return [
            'device' => Blade::render('<x-device-link :device="$device"/>', ['device' => $entPhysical->device]),
            'mfg' => htmlspecialchars((string) ($entPhysical->entPhysicalMfgName ?: 'N/A')),
            'class' => htmlspecialchars((string) ucfirst($entPhysical->entPhysicalClass ?: 'N/A')),
            'descr' => htmlspecialchars((string) ($entPhysical->entPhysicalDescr ?? '')),
            'name' => htmlspecialchars((string) ($entPhysical->entPhysicalName ?? '')),
            'model' => htmlspecialchars((string) $model),
            'serial' => htmlspecialchars((string) $serial),
            'hw_rev' => htmlspecialchars((string) $hwRev),
            'fw_rev' => htmlspecialchars((string) ($entPhysical->entPhysicalFirmwareRev ?: 'N/A')),
            'sw_rev' => htmlspecialchars((string) $swRev),
            'fru' => $entPhysical->entPhysicalIsFRU ? '<span class="label label-success">Yes</span>' : '<span class="label label-default">No</span>',
        ];
    }

    /**
     * Get headers for CSV export
     *
     * @return array
     */
    protected function getExportHeaders()
    {
        return [
            'Device',
            'Manufacturer',
            'Class',
            'Description',
            'Name',
            'Model',
            'Serial Number',
            'HW Revision',
            'FW Revision',
            'SW Revision',
            'FRU',
        ];
    }

    /**
     * Format a row for CSV export
     *
     * @param  EntPhysical  $entPhysical
     * @return array
     */
    protected function formatExportRow($entPhysical)
    {
        $hwRev = $entPhysical->entPhysicalHardwareRev ?: ($entPhysical->device->hardware ?? 'N/A');
        $swRev = $entPhysical->entPhysicalSoftwareRev ?: ($entPhysical->device->version ?? 'N/A');
        $model = $entPhysical->entPhysicalModelName ?: ($entPhysical->device->hardware ?? 'N/A');
        $serial = $entPhysical->entPhysicalSerialNum ?: ($entPhysical->device->serial ?? 'N/A');

        return [
            $entPhysical->device ? $entPhysical->device->displayName() : '',
            $entPhysical->entPhysicalMfgName ?: 'N/A',
            $entPhysical->entPhysicalClass ?: 'N/A',
            $entPhysical->entPhysicalDescr,
            $entPhysical->entPhysicalName,
            $model,
            $serial,
            $hwRev,
            $entPhysical->entPhysicalFirmwareRev ?: 'N/A',
            $swRev,
            $entPhysical->entPhysicalIsFRU ? 'Yes' : 'No',
        ];
    }
}
