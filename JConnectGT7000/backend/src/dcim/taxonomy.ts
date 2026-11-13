/** Ported from frontend/application/config/dcim_taxonomy.php */

export type DcimTaxonomyEntry = {
  category: string;
  domain: string;
  pue_role: string;
  subs: string[];
};

export const DCIM_TAXONOMY: DcimTaxonomyEntry[] = [
  {
    category: 'Power',
    domain: 'facility',
    pue_role: 'facility_overhead',
    subs: [
      'Utility / Incoming',
      'Transformer',
      'Switchgear / Distribution',
      'Protection',
      'UPS System',
      'Battery / BMS',
      'Generator',
      'ATS / STS',
      'PDU / iPDU / RPP',
      'Power Meter / Energy Meter',
      'Power Quality Meter',
      'Branch Circuit Monitor (BCM)',
      'Power Distribution Unit (PDU)',
      'Smart Power Cable',
    ],
  },
  {
    category: 'Cooling',
    domain: 'facility',
    pue_role: 'facility_overhead',
    subs: [
      'Chiller',
      'Cooling Tower',
      'CRAC / PAC',
      'InRow Cooling',
      'AHU / FCU',
      'Pump',
      'Valve / Actuator',
      'VFD',
      'Cooling Energy Meter (BTU)',
      'Flow Meter (CHW/CW)',
    ],
  },
  {
    category: 'Environment',
    domain: 'facility',
    pue_role: 'unknown',
    subs: [
      'Environmental Sensor',
      'Environmental Monitoring System',
      'Environmental Monitoring Gateway',
      'Temperature Sensor',
      'Humidity Sensor',
      'Temp/Humidity Combo Sensor',
      'Differential Pressure Sensor',
      'Airflow Sensor',
      'Water Leak Detection',
      'Smoke / Particulate',
      'CO / CO₂ Sensor',
      'Vibration Sensor',
      'Noise Sensor',
      'Weather Station',
    ],
  },
  {
    category: 'Fire & Safety',
    domain: 'facility',
    pue_role: 'unknown',
    subs: [
      'Fire Alarm Control Panel (FCP)',
      'Smoke Detector',
      'Heat Detector',
      'Gas Suppression',
      'EPO / Emergency',
      'Fire Pump Controller',
    ],
  },
  {
    category: 'Physical Security',
    domain: 'security',
    pue_role: 'unknown',
    subs: [
      'Access Control Panel',
      'Door Controller',
      'Card/Biometric Reader',
      'Door Contact / Intrusion',
      'CCTV Camera',
      'NVR / VMS',
      'Intercom',
    ],
  },
  {
    category: 'CCTV / Video',
    domain: 'security',
    pue_role: 'unknown',
    subs: [
      'IP Camera / RTSP',
      'PTZ Camera',
      'Analog Camera',
      'Door / Entrance Camera',
      'NVR',
      'VMS',
      'Video Encoder / Decoder',
    ],
  },
  {
    category: 'Racks & Space',
    domain: 'space',
    pue_role: 'unknown',
    subs: ['Rack Cabinet', 'Rack Sensor Hub', 'Rack Door Sensor', 'Rack Lock', 'Rack Light', 'Patch Panel'],
  },
  {
    category: 'Building / BMS / OT Integration',
    domain: 'integration',
    pue_role: 'unknown',
    subs: [
      'BMS Supervisor/Server',
      'DDC/PLC Controller',
      'Protocol Gateway',
      'Edge Gateway',
      'Data Logger',
      'NTP/PTP Time Source',
    ],
  },
  {
    category: 'IT Compute',
    domain: 'it',
    pue_role: 'it_load',
    subs: ['Server', 'Blade Chassis', 'HCI Node', 'GPU Server', 'BMC/iDRAC/iLO'],
  },
  {
    category: 'IT Storage',
    domain: 'it',
    pue_role: 'it_load',
    subs: ['SAN Array', 'NAS', 'Disk Shelf / JBOD', 'Tape Library', 'Backup Appliance'],
  },
  {
    category: 'IT Network',
    domain: 'it',
    pue_role: 'it_load',
    subs: [
      'Core Switch',
      'Distribution Switch',
      'ToR Switch',
      'Router',
      'Firewall',
      'Load Balancer',
      'WLC / AP',
      'Optical / DWDM',
    ],
  },
  {
    category: 'IT Support / OOB',
    domain: 'it',
    pue_role: 'unknown',
    subs: [
      'Console Server',
      'KVM',
      'IP KVM',
      'Serial-to-Ethernet',
      'Monitoring Appliance',
      'Log Collector',
    ],
  },
];

export type ResolvedDcim = {
  category: string;
  subCategory: string;
  domain: string;
  pueRole: string;
  label: string;
};

export function resolveDcimSelection(
  categoryName: string,
  subCategoryName: string,
): ResolvedDcim | null {
  const category = categoryName.trim();
  const subCategory = subCategoryName.trim();
  for (const entry of DCIM_TAXONOMY) {
    if (entry.category !== category || !entry.subs.includes(subCategory)) continue;
    return {
      category,
      subCategory,
      domain: entry.domain,
      pueRole: entry.pue_role,
      label: `${category} / ${subCategory}`,
    };
  }
  return null;
}

const LEGACY_CATEGORY_MAP: Record<string, [string, string]> = {
  UPS: ['Power', 'UPS System'],
  'Power Meter': ['Power', 'Power Meter / Energy Meter'],
  'Temperature/Humidity': ['Environment', 'Temp/Humidity Combo Sensor'],
  PDU: ['Power', 'PDU / iPDU / RPP'],
  Generator: ['Power', 'Generator'],
  CRAC: ['Cooling', 'CRAC / PAC'],
  'Fire Suppression': ['Fire & Safety', 'Gas Suppression'],
  'Access Control': ['Physical Security', 'Access Control Panel'],
  CCTV: ['Physical Security', 'CCTV Camera'],
  Network: ['IT Network', 'Core Switch'],
  Other: ['Building / BMS / OT Integration', 'Edge Gateway'],
};

export function inferDcimSelection(input: {
  deviceInformation?: Record<string, unknown> | null;
  categoryName?: string | null;
}): { category: string; subCategory: string } {
  const info = input.deviceInformation ?? {};
  const candidates: Array<[string, string]> = [];

  const infoCat = typeof info.dcim_category === 'string' ? info.dcim_category : '';
  const infoSub = typeof info.dcim_sub_category === 'string' ? info.dcim_sub_category : '';
  if (infoCat && infoSub) {
    candidates.push([infoCat, infoSub]);
  }

  const categoryName = (input.categoryName ?? '').trim();
  for (const entry of DCIM_TAXONOMY) {
    for (const sub of entry.subs) {
      if (categoryName === `${entry.category} / ${sub}`) {
        candidates.push([entry.category, sub]);
      }
    }
  }

  if (LEGACY_CATEGORY_MAP[categoryName]) {
    candidates.push(LEGACY_CATEGORY_MAP[categoryName]);
  }

  for (const [cat, sub] of candidates) {
    const resolved = resolveDcimSelection(cat, sub);
    if (resolved) {
      return { category: resolved.category, subCategory: resolved.subCategory };
    }
  }

  const first = DCIM_TAXONOMY[0];
  return {
    category: first?.category ?? '',
    subCategory: first?.subs[0] ?? '',
  };
}

export function injectDcimIntoDeviceInformation(
  info: Record<string, unknown>,
  taxonomy: ResolvedDcim,
): Record<string, unknown> {
  return {
    ...info,
    domain: taxonomy.domain,
    pue_role: taxonomy.pueRole,
    dcim_category: taxonomy.category,
    dcim_sub_category: taxonomy.subCategory,
  };
}
