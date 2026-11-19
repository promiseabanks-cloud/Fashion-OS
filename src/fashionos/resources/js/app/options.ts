/**
 * Options offered in workspace and team forms.
 *
 * Timezones are a deliberate subset: enough for the initial market and its
 * neighbours, validated again on the server against the full IANA list.
 */
export const TIMEZONES: Array<{ value: string; label: string }> = [
  { value: 'Africa/Lagos', label: 'Lagos (WAT)' },
  { value: 'Africa/Accra', label: 'Accra (GMT)' },
  { value: 'Africa/Abuja', label: 'Abuja (WAT)' },
  { value: 'Africa/Nairobi', label: 'Nairobi (EAT)' },
  { value: 'Africa/Johannesburg', label: 'Johannesburg (SAST)' },
  { value: 'Africa/Cairo', label: 'Cairo (EET)' },
  { value: 'Europe/London', label: 'London (GMT/BST)' },
  { value: 'Europe/Paris', label: 'Paris (CET)' },
  { value: 'America/New_York', label: 'New York (ET)' },
  { value: 'America/Los_Angeles', label: 'Los Angeles (PT)' },
  { value: 'Asia/Dubai', label: 'Dubai (GST)' },
  { value: 'UTC', label: 'UTC' },
];

export const CURRENCIES: Array<{ value: string; label: string }> = [
  { value: 'NGN', label: 'NGN — Nigerian naira' },
  { value: 'USD', label: 'USD — US dollar' },
  { value: 'GBP', label: 'GBP — Pound sterling' },
  { value: 'EUR', label: 'EUR — Euro' },
  { value: 'GHS', label: 'GHS — Ghanaian cedi' },
  { value: 'KES', label: 'KES — Kenyan shilling' },
  { value: 'ZAR', label: 'ZAR — South African rand' },
  { value: 'CAD', label: 'CAD — Canadian dollar' },
];
