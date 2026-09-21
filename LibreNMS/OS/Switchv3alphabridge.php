<?php

namespace LibreNMS\OS;

use App\Facades\PortCache;
use App\Models\Transceiver;
use Illuminate\Support\Collection;
use LibreNMS\Interfaces\Discovery\TransceiverDiscovery;
use LibreNMS\OS;
use SnmpQuery;

class Switchv3alphabridge extends OS implements TransceiverDiscovery
{
    public function discoverTransceivers(): Collection
    {
        $device = $this->getDevice()->toArray();

        if (function_exists('snmpwalk_cache_oid')) {
            $models = snmpwalk_cache_oid($device, '.1.3.6.1.4.1.58158.9.63.1.7.1.44', [], '');
            $serials = snmpwalk_cache_oid($device, '.1.3.6.1.4.1.58158.9.63.1.7.1.23', [], '');
            $wavelengths = snmpwalk_cache_oid($device, '.1.3.6.1.4.1.58158.9.63.1.7.1.19', [], '');
            $distances = snmpwalk_cache_oid($device, '.1.3.6.1.4.1.58158.9.63.1.7.1.18', [], '');
            $vendors = snmpwalk_cache_oid($device, '.1.3.6.1.4.1.58158.9.63.1.7.1.7', [], '');

            $transceivers = collect();

            if (! empty($models)) {
                foreach ($models as $key => $data) {
                    $ifIndex = (int) last(explode('.', $key));
                    $model = ! empty($data) ? trim((string) array_values($data)[0]) : null;

                    // Skip empty models
                    if (empty($model)) {
                        continue;
                    }

                    $vendorKey = "58158.9.63.1.7.1.7.{$ifIndex}";
                    $vendorArr = $vendors[$vendorKey] ?? [];
                    $vendorVal = ! empty($vendorArr) ? trim((string) array_values($vendorArr)[0]) : null;

                    $distKey = "58158.9.63.1.7.1.18.{$ifIndex}";
                    $distArr = $distances[$distKey] ?? [];
                    $distVal = ! empty($distArr) ? (int) array_values($distArr)[0] : null;

                    $waveKey = "58158.9.63.1.7.1.19.{$ifIndex}";
                    $waveArr = $wavelengths[$waveKey] ?? [];
                    $waveVal = ! empty($waveArr) ? (int) array_values($waveArr)[0] : null;

                    $serialKey = "58158.9.63.1.7.1.23.{$ifIndex}";
                    $serialArr = $serials[$serialKey] ?? [];
                    $serialVal = ! empty($serialArr) ? trim((string) array_values($serialArr)[0]) : null;

                    $portId = (int) PortCache::getIdFromIfIndex($ifIndex, $this->getDevice());

                    $transceivers->push(new Transceiver([
                        'port_id' => $portId,
                        'index' => $ifIndex,
                        'vendor' => $vendorVal ?: 'Alpha Bridge',
                        'type' => str_contains((string) $model, '10G') ? 'SFP+' : 'SFP',
                        'model' => $model,
                        'serial' => $serialVal ?: null,
                        'wavelength' => ($waveVal && $waveVal > 0) ? (string) $waveVal : null,
                        'distance' => ($distVal && $distVal > 0) ? (string) $distVal : null,
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
