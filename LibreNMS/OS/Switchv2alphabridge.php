<?php

namespace LibreNMS\OS;

use App\Facades\PortCache;
use App\Models\Transceiver;
use Illuminate\Support\Collection;
use LibreNMS\Interfaces\Discovery\TransceiverDiscovery;
use LibreNMS\OS;
use SnmpQuery;

class Switchv2alphabridge extends OS implements TransceiverDiscovery
{
    public function discoverTransceivers(): Collection
    {
        $device = $this->getDevice()->toArray();

        if (function_exists('snmpwalk_cache_oid')) {
            $models = snmpwalk_cache_oid($device, '.1.3.6.1.4.1.58158.9.63.1.7.1.44', [], '');
            $statuses = snmpwalk_cache_oid($device, '.1.3.6.1.4.1.58158.9.63.1.7.1.21', [], '');

            $transceivers = collect();

            if (! empty($models)) {
                foreach ($models as $key => $data) {
                    $ifIndex = (int) last(explode('.', $key));
                    $statusKey = "58158.9.63.1.7.1.21.{$ifIndex}";
                    $statusArr = $statuses[$statusKey] ?? [];
                    $status = ! empty($statusArr) ? array_values($statusArr)[0] : null;
                    $model = ! empty($data) ? array_values($data)[0] : null;

                    // Skip empty models or non-inserted ports (status 2 = absent)
                    if (empty($model) || $status == 2) {
                        continue;
                    }

                    $portId = (int) PortCache::getIdFromIfIndex($ifIndex, $this->getDevice());

                    $transceivers->push(new Transceiver([
                        'port_id' => $portId,
                        'index' => $ifIndex,
                        'vendor' => 'Alpha Bridge',
                        'type' => str_contains((string) $model, '10G') ? 'SFP+' : 'SFP',
                        'model' => trim((string) $model),
                        'ddm' => true,
                        'entity_physical_index' => $ifIndex,
                    ]));
                }
            }

            if ($transceivers->isNotEmpty()) {
                return $transceivers;
            }
        }

        // Fallback: NMS-OPTICAL-PORT-MIB::opticalPortPowerTable
        return SnmpQuery::cache()->walk('NMS-OPTICAL-PORT-MIB::opticalPortPowerTable')->mapTable(function ($data, $ifIndex) {
            $descr = $data['NMS-OPTICAL-PORT-MIB::opIfDescr'] ?? null;
            $rxPower = $data['NMS-OPTICAL-PORT-MIB::opIfRxPowerCurr'] ?? null;
            $txPower = $data['NMS-OPTICAL-PORT-MIB::opIfTxPowerCurr'] ?? null;

            if ($rxPower === null && $txPower === null) {
                return null;
            }

            return new Transceiver([
                'port_id' => (int) PortCache::getIdFromIfIndex($ifIndex, $this->getDevice()),
                'index' => $ifIndex,
                'vendor' => 'Alpha Bridge',
                'type' => 'SFP',
                'model' => $descr ?: 'SFP Transceiver',
                'ddm' => true,
                'entity_physical_index' => $ifIndex,
            ]);
        })->filter();
    }
}
