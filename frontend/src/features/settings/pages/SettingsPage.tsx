import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { fetchSettings, updateSettings } from '@/api/endpoints/settings'
import { queryKeys } from '@/api/queryKeys'
import { usePermission } from '@/hooks/usePermission'
import { PageHeader } from '@/components/layout/PageHeader'
import { Button, FormField, Input, Skeleton, Switch, Tabs } from '@/components/ui'
import type { SettingUpdateItem, SettingsMap } from '@/types/settings'
import type { ApiError } from '@/api/client'

interface FieldConfig {
  key: string
  label: string
  hint?: string
  group: string
  type: 'string' | 'integer' | 'boolean'
  isPublic?: boolean
  inputType?: string
}

const FIELDS: FieldConfig[] = [
  { key: 'branding.primary_color', label: 'Primary color', group: 'branding', type: 'string', isPublic: true, inputType: 'color' },
  { key: 'branding.secondary_color', label: 'Secondary color', group: 'branding', type: 'string', isPublic: true, inputType: 'color' },
  { key: 'branding.logo_url', label: 'Logo URL', group: 'branding', type: 'string', isPublic: true },
  { key: 'branding.favicon_url', label: 'Favicon URL', group: 'branding', type: 'string', isPublic: true },
  { key: 'localization.currency', label: 'Currency code', hint: 'e.g. USD, EUR, GBP', group: 'localization', type: 'string', isPublic: true },
  { key: 'localization.currency_symbol', label: 'Currency symbol', group: 'localization', type: 'string', isPublic: true },
  { key: 'localization.timezone', label: 'Timezone', hint: 'e.g. America/Chicago', group: 'localization', type: 'string', isPublic: true },
  { key: 'localization.date_format', label: 'Date format', hint: 'date-fns pattern, e.g. yyyy-MM-dd', group: 'localization', type: 'string', isPublic: true },
  { key: 'academic.grade_level_label', label: 'Grade level terminology', hint: 'e.g. "Grade" or "Class"', group: 'academic', type: 'string', isPublic: true },
  { key: 'academic.section_label', label: 'Section terminology', group: 'academic', type: 'string', isPublic: true },
  { key: 'academic.term_label', label: 'Term terminology', hint: 'e.g. "Term" or "Semester"', group: 'academic', type: 'string', isPublic: true },
  { key: 'students.admission_number_format', label: 'Admission number format', hint: 'Tokens: {SCHOOL} {YEAR} {SEQ}', group: 'students', type: 'string' },
  { key: 'students.admission_number_padding', label: 'Admission number padding', hint: 'Digits in the sequence, e.g. 4 → 0001', group: 'students', type: 'integer' },
  { key: 'notifications.email_enabled', label: 'Email notifications enabled', group: 'notifications', type: 'boolean' },
  {
    key: 'retention.activity_log_days',
    label: 'Audit log retention (days)',
    hint: 'Audit log entries older than this are pruned nightly. Default 365.',
    group: 'retention',
    type: 'integer',
  },
  {
    key: 'retention.data_export_days',
    label: 'Data export availability (days)',
    hint: 'How long a generated export ZIP stays downloadable before it\'s deleted. Default 7.',
    group: 'retention',
    type: 'integer',
  },
  {
    key: 'retention.inactive_account_anonymize_days',
    label: 'Auto-anonymize inactive accounts after (days)',
    hint: 'Leave blank to disable (default). If set, accounts with no login for this many days are automatically anonymized.',
    group: 'retention',
    type: 'integer',
  },
]

export function SettingsPage() {
  const { can } = usePermission()
  const queryClient = useQueryClient()
  const { data, isLoading } = useQuery({ queryKey: queryKeys.settings, queryFn: fetchSettings })
  const [values, setValues] = useState<SettingsMap>({})

  useEffect(() => {
    if (data) setValues(data)
  }, [data])

  const mutation = useMutation({
    mutationFn: updateSettings,
    onSuccess: (updated) => {
      toast.success('Settings saved.')
      queryClient.setQueryData(queryKeys.settings, updated)
      queryClient.invalidateQueries({ queryKey: queryKeys.publicSettings() })
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  function submitGroup(group: string) {
    const settings: SettingUpdateItem[] = FIELDS.filter((field) => field.group === group).map((field) => ({
      key: field.key,
      value: values[field.key],
      type: field.type,
      group: field.group,
      is_public: field.isPublic ?? false,
    }))
    mutation.mutate({ settings })
  }

  function renderField(field: FieldConfig) {
    const value = values[field.key]
    return (
      <FormField key={field.key} label={field.label} htmlFor={field.key} hint={field.hint}>
        {field.type === 'boolean' ? (
          <Switch checked={!!value} onCheckedChange={(checked) => setValues({ ...values, [field.key]: checked })} />
        ) : (
          <Input
            id={field.key}
            type={field.inputType ?? (field.type === 'integer' ? 'number' : 'text')}
            value={(value as string | number | undefined) ?? ''}
            onChange={(e) => setValues({ ...values, [field.key]: field.type === 'integer' ? Number(e.target.value) : e.target.value })}
          />
        )}
      </FormField>
    )
  }

  function groupPanel(group: string, description: string) {
    return (
      <div className="flex flex-col gap-4">
        <p className="text-sm text-muted-foreground">{description}</p>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">{FIELDS.filter((field) => field.group === group).map(renderField)}</div>
        {can('settings.edit') && (
          <div>
            <Button onClick={() => submitGroup(group)} isLoading={mutation.isPending}>
              Save changes
            </Button>
          </div>
        )}
      </div>
    )
  }

  return (
    <div>
      <PageHeader title="Settings" description="Configure branding, localization, terminology, and system behavior." />
      {isLoading ? (
        <Skeleton className="h-64 w-full" />
      ) : (
        <Tabs
          items={[
            { value: 'branding', label: 'Branding', content: groupPanel('branding', 'Colors and logo shown throughout the app and login screen.') },
            { value: 'localization', label: 'Localization', content: groupPanel('localization', 'Currency, timezone, and date formatting.') },
            { value: 'academic', label: 'Academic Terminology', content: groupPanel('academic', 'Customize labels to match your school system.') },
            { value: 'students', label: 'Students', content: groupPanel('students', 'Admission number generation rules.') },
            { value: 'notifications', label: 'Notifications', content: groupPanel('notifications', 'Enable or disable notification channels.') },
            { value: 'retention', label: 'Retention', content: groupPanel('retention', 'How long audit logs and data exports are kept, and optional auto-anonymization of inactive accounts.') },
          ]}
        />
      )}
    </div>
  )
}
