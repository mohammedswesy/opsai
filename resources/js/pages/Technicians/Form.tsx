import { Button } from '@/components/Button';
import { Card, CardForm } from '@/components/Card';
import { Link, useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { FormField, controlClassName } from '@/components/FormField';
import { FormPage } from '@/components/FormPage';
import { PageHeader } from '@/components/PageHeader';
import { useI18n } from '@/i18n';
import { optionLabel } from '@/lib/labels';
import AppLayout from '@/layouts/AppLayout';
import { AVAILABILITY_STATUSES, SKILLS } from '@/lib/options';
import type { Option } from '@/types/page';

type TechnicianForm = {
    id: string;
    user_id: string;
    branch_id: string | null;
    employee_code: string | null;
    availability_status: string;
    hourly_rate: string | null;
    status: string;
    skills: { skill: string; service_id: string | null }[];
};

export default function Form({
    technician,
    branches,
    members,
    services,
}: {
    technician: TechnicianForm | null;
    branches: Option[];
    members: Option[];
    services: Option[];
}) {
    const { t } = useI18n();
    const form = useForm({
        user_id: technician?.user_id ?? '',
        branch_id: technician?.branch_id ?? '',
        employee_code: technician?.employee_code ?? '',
        availability_status: technician?.availability_status ?? 'available',
        hourly_rate: technician?.hourly_rate ?? '',
        status: technician?.status ?? 'active',
        skills: technician?.skills?.map((skill) => ({ skill: skill.skill, service_id: skill.service_id ?? '' })) ?? [{ skill: 'hvac', service_id: '' }],
    });

    return (
        <FormPage>
            <PageHeader parent={{ label: t.nav.technicians, href: '/technicians' }} title={technician ? t.common.edit : t.ops.create} subtitle={t.nav.technicians} />
            <CardForm className="grid gap-4 p-5" onSubmit={(event) => {
                    event.preventDefault();
                    const payload = {
                        ...form.data,
                        branch_id: form.data.branch_id || null,
                        hourly_rate: form.data.hourly_rate === '' ? null : form.data.hourly_rate,
                        skills: form.data.skills.map((skill) => ({
                            skill: skill.skill,
                            service_id: skill.service_id || null,
                        })),
                    };
                    form.transform(() => payload);
                    if (technician) {
                        form.put(`/technicians/${technician.id}`);
                    } else {
                        form.post('/technicians');
                    }
                }}
            >
                {technician ? null : (
                    <FormField label={t.ops.member} error={form.errors.user_id}>
                        <select className={controlClassName} value={form.data.user_id} onChange={(event) => form.setData('user_id', event.target.value)}>
                            <option value="">{t.common.none}</option>
                            {members.map((member) => (
                                <option key={member.id} value={member.id}>
                                    {member.name}
                                </option>
                            ))}
                        </select>
                    </FormField>
                )}
                <FormField label={t.ops.employeeCode} error={form.errors.employee_code}>
                    <input className={controlClassName} value={form.data.employee_code} onChange={(event) => form.setData('employee_code', event.target.value)} />
                </FormField>
                <FormField label={t.ops.branch} error={form.errors.branch_id}>
                    <select className={controlClassName} value={form.data.branch_id} onChange={(event) => form.setData('branch_id', event.target.value)}>
                        <option value="">{t.common.none}</option>
                        {branches.map((branch) => (
                            <option key={branch.id} value={branch.id}>
                                {branch.name}
                            </option>
                        ))}
                    </select>
                </FormField>
                <FormField label={t.ops.availability} error={form.errors.availability_status}>
                    <select className={controlClassName} value={form.data.availability_status} onChange={(event) => form.setData('availability_status', event.target.value)}>
                        {AVAILABILITY_STATUSES.map((value) => (
                            <option key={value} value={value}>
                                {optionLabel(t.labels, value)}
                            </option>
                        ))}
                    </select>
                </FormField>
                <FormField label={t.ops.hourlyRate} error={form.errors.hourly_rate}>
                    <input className={controlClassName} value={form.data.hourly_rate} onChange={(event) => form.setData('hourly_rate', event.target.value)} />
                </FormField>
                <FormField label={t.ops.status} error={form.errors.status}>
                    <select className={controlClassName} value={form.data.status} onChange={(event) => form.setData('status', event.target.value)}>
                        <option value="active">{t.labels.active}</option>
                        <option value="inactive">{t.labels.inactive}</option>
                    </select>
                </FormField>
                <div className="space-y-2">
                    <p className="text-sm font-medium text-zinc-700">{t.ops.skills}</p>
                    {form.data.skills.map((skill, index) => (
                        <div key={index} className="grid gap-2 sm:grid-cols-2">
                            <select
                                className={controlClassName}
                                value={skill.skill}
                                onChange={(event) => {
                                    const skills = [...form.data.skills];
                                    skills[index] = { ...skill, skill: event.target.value };
                                    form.setData('skills', skills);
                                }}
                            >
                                {SKILLS.map((name) => (
                                    <option key={name} value={name}>
                                        {optionLabel(t.labels, name)}
                                    </option>
                                ))}
                            </select>
                            <select
                                className={controlClassName}
                                value={skill.service_id}
                                onChange={(event) => {
                                    const skills = [...form.data.skills];
                                    skills[index] = { ...skill, service_id: event.target.value };
                                    form.setData('skills', skills);
                                }}
                            >
                                <option value="">{t.common.none}</option>
                                {services.map((service) => (
                                    <option key={service.id} value={service.id}>
                                        {service.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                    ))}
                </div>
                <div className="flex gap-3">
                    <Button variant="primary" type="submit" disabled={form.processing}>
                        {t.common.save}
                    </Button>
                    <Link href="/technicians" className="px-4 py-2 text-sm text-zinc-700">
                        {t.common.cancel}
                    </Link>
                </div>
            </CardForm>
        </FormPage>
    );
}

Form.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
