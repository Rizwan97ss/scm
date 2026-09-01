import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
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
  labelKey: string
  hintKey?: string
  group: string
  type: 'string' | 'integer' | 'boolean'
  isPublic?: boolean
  inputType?: string
  /**
   * What a boolean field means when no row exists yet for it — a real
   * school only gets rows once an admin saves this group at least once
   * (school creation seeds roles/exam config, not Settings — see
   * SchoolProvisioningService), so "no row" is the normal state, not an
   * edge case. Without this, the toggle would render unchecked/off even
   * though the backend's own `get($key, true)` default is actually on,
   * silently lying to the admin about what's currently happening.
   */
  defaultValue?: boolean
}

const FIELDS: FieldConfig[] = [
  { key: 'branding.primary_color', labelKey: 'settings.brandingPrimaryColorLabel', group: 'branding', type: 'string', isPublic: true, inputType: 'color' },
  { key: 'branding.secondary_color', labelKey: 'settings.brandingSecondaryColorLabel', group: 'branding', type: 'string', isPublic: true, inputType: 'color' },
  { key: 'branding.logo_url', labelKey: 'settings.brandingLogoUrlLabel', group: 'branding', type: 'string', isPublic: true },
  { key: 'branding.favicon_url', labelKey: 'settings.brandingFaviconUrlLabel', group: 'branding', type: 'string', isPublic: true },
  { key: 'localization.currency', labelKey: 'settings.localizationCurrencyLabel', hintKey: 'settings.localizationCurrencyHint', group: 'localization', type: 'string', isPublic: true },
  { key: 'localization.currency_symbol', labelKey: 'settings.localizationCurrencySymbolLabel', group: 'localization', type: 'string', isPublic: true },
  { key: 'localization.timezone', labelKey: 'settings.localizationTimezoneLabel', hintKey: 'settings.localizationTimezoneHint', group: 'localization', type: 'string', isPublic: true },
  { key: 'localization.date_format', labelKey: 'settings.localizationDateFormatLabel', hintKey: 'settings.localizationDateFormatHint', group: 'localization', type: 'string', isPublic: true },
  { key: 'academic.grade_level_label', labelKey: 'settings.academicGradeLevelLabelLabel', hintKey: 'settings.academicGradeLevelLabelHint', group: 'academic', type: 'string', isPublic: true },
  { key: 'academic.section_label', labelKey: 'settings.academicSectionLabelLabel', group: 'academic', type: 'string', isPublic: true },
  { key: 'academic.term_label', labelKey: 'settings.academicTermLabelLabel', hintKey: 'settings.academicTermLabelHint', group: 'academic', type: 'string', isPublic: true },
  { key: 'students.admission_number_format', labelKey: 'settings.studentsAdmissionFormatLabel', hintKey: 'settings.studentsAdmissionFormatHint', group: 'students', type: 'string' },
  { key: 'students.admission_number_padding', labelKey: 'settings.studentsAdmissionPaddingLabel', hintKey: 'settings.studentsAdmissionPaddingHint', group: 'students', type: 'integer' },
  { key: 'notifications.email_enabled', labelKey: 'settings.notificationsEmailEnabledLabel', group: 'notifications', type: 'boolean' },
  { key: 'id_cards.staff.show_email', labelKey: 'settings.idCardsStaffShowEmailLabel', group: 'id_cards', type: 'boolean', defaultValue: true },
  { key: 'id_cards.staff.show_phone', labelKey: 'settings.idCardsStaffShowPhoneLabel', group: 'id_cards', type: 'boolean', defaultValue: true },
  { key: 'id_cards.staff.show_website', labelKey: 'settings.idCardsStaffShowWebsiteLabel', group: 'id_cards', type: 'boolean', defaultValue: true },
  { key: 'id_cards.staff.show_barcode', labelKey: 'settings.idCardsStaffShowBarcodeLabel', group: 'id_cards', type: 'boolean', defaultValue: true },
  { key: 'id_cards.student.show_dob', labelKey: 'settings.idCardsStudentShowDobLabel', group: 'id_cards', type: 'boolean', defaultValue: true },
  { key: 'id_cards.student.show_address', labelKey: 'settings.idCardsStudentShowAddressLabel', group: 'id_cards', type: 'boolean', defaultValue: true },
  { key: 'id_cards.student.show_barcode', labelKey: 'settings.idCardsStudentShowBarcodeLabel', group: 'id_cards', type: 'boolean', defaultValue: true },
  {
    key: 'retention.activity_log_days',
    labelKey: 'settings.retentionActivityLogDaysLabel',
    hintKey: 'settings.retentionActivityLogDaysHint',
    group: 'retention',
    type: 'integer',
  },
  {
    key: 'retention.data_export_days',
    labelKey: 'settings.retentionDataExportDaysLabel',
    hintKey: 'settings.retentionDataExportDaysHint',
    group: 'retention',
    type: 'integer',
  },
  {
    key: 'retention.inactive_account_anonymize_days',
    labelKey: 'settings.retentionAnonymizeDaysLabel',
    hintKey: 'settings.retentionAnonymizeDaysHint',
    group: 'retention',
    type: 'integer',
  },
]

/** No stored row yet (see FieldConfig.defaultValue's docblock) falls back to the field's declared default rather than reading as off. */
function resolveBooleanValue(field: FieldConfig, values: SettingsMap): boolean {
  const value = values[field.key]
  return value === undefined || value === null ? (field.defaultValue ?? false) : !!value
}

export function SettingsPage() {
  const { t } = useTranslation()
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
      toast.success(t('settings.settingsSavedToast'))
      queryClient.setQueryData(queryKeys.settings, updated)
      queryClient.invalidateQueries({ queryKey: queryKeys.publicSettings() })
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  function submitGroup(group: string) {
    const settings: SettingUpdateItem[] = FIELDS.filter((field) => field.group === group).map((field) => ({
      key: field.key,
      // A field the admin never touched (no row exists yet -- see
      // defaultValue's own docblock) is undefined/null here, not its
      // actual displayed state -- resolving through the same fallback the
      // toggle renders with keeps "save because I changed one other field
      // in this tab" from silently persisting every untouched boolean in
      // it as off.
      value: field.type === 'boolean' ? resolveBooleanValue(field, values) : values[field.key],
      type: field.type,
      group: field.group,
      is_public: field.isPublic ?? false,
    }))
    mutation.mutate({ settings })
  }

  function renderField(field: FieldConfig) {
    const value = values[field.key]
    return (
      <FormField key={field.key} label={t(field.labelKey)} htmlFor={field.key} hint={field.hintKey ? t(field.hintKey) : undefined}>
        {field.type === 'boolean' ? (
          <Switch checked={resolveBooleanValue(field, values)} onCheckedChange={(checked) => setValues({ ...values, [field.key]: checked })} />
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
              {t('common.saveChanges')}
            </Button>
          </div>
        )}
      </div>
    )
  }

  return (
    <div>
      <PageHeader title={t('nav.settings')} description={t('settings.pageDescription')} />
      {isLoading ? (
        <Skeleton className="h-64 w-full" />
      ) : (
        <Tabs
          items={[
            { value: 'branding', label: t('settings.tabBrandingLabel'), content: groupPanel('branding', t('settings.tabBrandingDescription')) },
            { value: 'localization', label: t('settings.tabLocalizationLabel'), content: groupPanel('localization', t('settings.tabLocalizationDescription')) },
            { value: 'academic', label: t('settings.tabAcademicLabel'), content: groupPanel('academic', t('settings.tabAcademicDescription')) },
            { value: 'students', label: t('nav.students'), content: groupPanel('students', t('settings.tabStudentsDescription')) },
            { value: 'id_cards', label: t('settings.tabIdCardsLabel'), content: groupPanel('id_cards', t('settings.tabIdCardsDescription')) },
            { value: 'notifications', label: t('settings.tabNotificationsLabel'), content: groupPanel('notifications', t('settings.tabNotificationsDescription')) },
            { value: 'retention', label: t('settings.tabRetentionLabel'), content: groupPanel('retention', t('settings.tabRetentionDescription')) },
          ]}
        />
      )}
    </div>
  )
}
