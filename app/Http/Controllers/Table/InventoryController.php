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
            'entPhysicalFirmwareRev',
            'entPhysicalSoftwareRev',
        ];
    }

    protected function sortFields($request)
    {
        return [
            'device_ip' => 'device_id',
            'hostname' => 'device_id',
            'mfg' => 'entPhysicalMfgName',
            'name' => 'entPhysicalName',
            'descr' => 'entPhysicalDescr',
            'serial' => 'entPhysicalSerialNum',
            'fw_rev' => 'entPhysicalFirmwareRev',
            'sw_rev' => 'entPhysicalSoftwareRev',
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
                'entPhysicalFirmwareRev',
                'entPhysicalSoftwareRev',
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
        $device = $entPhysical->device;
        $deviceIp = $device
            ? htmlspecialchars((string) ($device->overwrite_ip ?: (\LibreNMS\Util\IP::isValid($device->hostname) ? $device->hostname : $device->ip)))
            : '';
        $hostnameLink = $device
            ? '<a href="' . route('device', ['device' => $device->device_id]) . '">' . htmlspecialchars((string) ($device->sysName ?: $device->hostname)) . '</a>'
            : '';

        return [
            'device_ip' => $deviceIp,
            'hostname' => $hostnameLink,
            'mfg' => htmlspecialchars((string) ($entPhysical->entPhysicalMfgName ?: '')),
            'descr' => htmlspecialchars((string) ($entPhysical->entPhysicalDescr ?? '')),
            'name' => htmlspecialchars((string) ($entPhysical->entPhysicalName ?? '')),
            'serial' => htmlspecialchars((string) ($entPhysical->entPhysicalSerialNum ?: '')),
            'fw_rev' => htmlspecialchars((string) ($entPhysical->entPhysicalFirmwareRev ?: '')),
            'sw_rev' => htmlspecialchars((string) ($entPhysical->entPhysicalSoftwareRev ?: '')),
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
            'Device IP',
            'Hostname',
            'Manufacturer',
            'Description',
            'Name',
            'Serial Number',
            'FW Revision',
            'SW Revision',
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
        $device = $entPhysical->device;

        return [
            $device ? ($device->overwrite_ip ?: (\LibreNMS\Util\IP::isValid($device->hostname) ? $device->hostname : $device->ip)) : '',
            $device ? ($device->sysName ?: $device->hostname) : '',
            $entPhysical->entPhysicalMfgName ?: '',
            $entPhysical->entPhysicalDescr ?: '',
            $entPhysical->entPhysicalName ?: '',
            $entPhysical->entPhysicalSerialNum ?: '',
            $entPhysical->entPhysicalFirmwareRev ?: '',
            $entPhysical->entPhysicalSoftwareRev ?: '',
        ];
    }
}
