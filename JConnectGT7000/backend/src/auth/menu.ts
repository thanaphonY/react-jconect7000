export interface MenuItem {
  labelKey: string;
  path: string;
}

interface MenuRule {
  labelKey: string;
  path: string;
  module?: string;
}

const MENU_RULES: MenuRule[] = [
  { labelKey: 'home', path: '/home' },
  { labelKey: 'dashboard', path: '/dashboard', module: 'dashboard' },
  { labelKey: 'devices', path: '/device', module: 'devices' },
  { labelKey: 'notification', path: '/notification_manager', module: 'notification' },
  { labelKey: 'profile', path: '/profile' },
  { labelKey: 'users', path: '/user/userlist', module: 'user' },
  { labelKey: 'auditlogs', path: '/eventlogs', module: 'eventlog' },
  { labelKey: 'alarmlog', path: '/alarmlogs', module: 'alarmlogs' },
  { labelKey: 'reports', path: '/reports', module: 'reports' },
  { labelKey: 'device_template', path: '/devicetemplate', module: 'devicetemplate' },
  { labelKey: 'settings', path: '/setting', module: 'setting' },
];

type PermissionData = Record<string, Record<string, unknown>>;

function isAllowedFlag(value: unknown): boolean {
  return value === '1' || value === 1 || value === true;
}

export function canReadModule(permission: PermissionData | null, moduleName: string): boolean {
  return canModuleMethod(permission, moduleName, 'own_read') || canModuleMethod(permission, moduleName, 'all_read');
}

export function canModuleMethod(
  permission: PermissionData | null,
  moduleName: string,
  method: string,
): boolean {
  if (!permission) {
    return false;
  }
  const entry = Object.entries(permission).find(([key]) => key.toLowerCase() === moduleName.toLowerCase());
  if (!entry) {
    return false;
  }
  return isAllowedFlag(entry[1][method]);
}

export function isAdminUserType(userType: string | null): boolean {
  return userType === 'admin' || userType === 'Admin';
}

export function menuForUser(userType: string | null, permission: PermissionData | null): MenuItem[] {
  const admin = isAdminUserType(userType);
  return MENU_RULES.filter((rule) => {
    if (!rule.module || admin) {
      return true;
    }
    return canReadModule(permission, rule.module);
  }).map((rule) => ({ labelKey: rule.labelKey, path: rule.path }));
}

export function parsePermissionData(raw: string | null): PermissionData | null {
  if (!raw) {
    return null;
  }
  try {
    const parsed: unknown = JSON.parse(raw);
    if (typeof parsed !== 'object' || parsed === null || Array.isArray(parsed)) {
      return null;
    }
    return parsed as PermissionData;
  } catch {
    return null;
  }
}
