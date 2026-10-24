/*
 * Option values mirrored from app/Enums. Keep the order in sync with the enum cases;
 * labels come from t.labels through optionLabel().
 */

export const PRIORITIES = ['low', 'normal', 'high', 'urgent'] as const;
export const RECORD_STATUSES = ['active', 'inactive'] as const;
export const CUSTOMER_TYPES = ['individual', 'company'] as const;
export const REQUEST_SOURCES = ['manual', 'whatsapp', 'website', 'phone', 'email', 'ai'] as const;
export const REQUEST_STATUSES = ['new', 'qualified', 'scheduled', 'in_progress', 'completed', 'cancelled'] as const;
export const APPOINTMENT_STATUSES = ['scheduled', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show'] as const;
export const WORK_ORDER_STATUSES = ['assigned', 'accepted', 'on_the_way', 'arrived', 'in_progress', 'completed', 'cancelled'] as const;
export const TASK_STATUSES = ['todo', 'in_progress', 'completed', 'cancelled'] as const;
export const INVOICE_STATUSES = ['draft', 'issued', 'partially_paid', 'paid', 'overdue', 'cancelled'] as const;
export const PAYMENT_METHODS = ['cash', 'bank_transfer', 'card', 'online', 'other'] as const;
export const AVAILABILITY_STATUSES = ['available', 'busy', 'unavailable', 'off_duty'] as const;
export const SKILLS = ['hvac', 'electrical', 'plumbing', 'cleaning', 'pest_control'] as const;
export const ROLES = ['owner', 'admin', 'manager', 'dispatcher', 'technician', 'accountant', 'customer_service'] as const;
