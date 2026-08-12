import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useQuery } from '@tanstack/react-query'
import { Check, ChevronLeft, ChevronRight } from 'lucide-react'
import { signupSchema, SIGNUP_STEP_FIELDS, type SignupFormValues } from '../schemas/signupSchema'
import { signup } from '@/api/endpoints/auth'
import { plansApi } from '@/api/endpoints/plans'
import { queryKeys } from '@/api/queryKeys'
import { AuthLayout } from '@/features/marketing/components/AuthLayout'
import { Button, Card, CardContent, FormField, Input } from '@/components/ui'
import { cn } from '@/utils/cn'
import type { ApiError } from '@/api/client'

const STEPS = ['school', 'admin', 'plan'] as const
type Step = (typeof STEPS)[number]

const STEP_LABELS: Record<Step, string> = { school: 'Your school', admin: 'Your account', plan: 'Choose a plan' }

function stepForField(field: string): Step {
  if (field.startsWith('school.')) return 'school'
  if (field.startsWith('admin.')) return 'admin'
  return 'plan'
}

export function SignupPage() {
  const [stepIndex, setStepIndex] = useState(0)
  const [formError, setFormError] = useState<string | null>(null)
  const [isRedirecting, setIsRedirecting] = useState(false)

  const { data: plans, isLoading: plansLoading } = useQuery({ queryKey: queryKeys.plans, queryFn: plansApi.list })

  const {
    register,
    handleSubmit,
    watch,
    setValue,
    setError,
    trigger,
    formState: { errors, isSubmitting },
  } = useForm<SignupFormValues>({
    resolver: zodResolver(signupSchema),
    defaultValues: {
      school: { name: '', short_name: '', email: '', phone: '' },
      admin: { first_name: '', last_name: '', email: '', password: '', password_confirmation: '' },
    },
  })

  const step = STEPS[stepIndex]
  const planId = watch('plan_id')

  async function goNext() {
    const fields = SIGNUP_STEP_FIELDS[step]
    const valid = await trigger(fields as unknown as (keyof SignupFormValues)[])
    if (valid) setStepIndex((i) => Math.min(i + 1, STEPS.length - 1))
  }

  function goBack() {
    setStepIndex((i) => Math.max(i - 1, 0))
  }

  async function onSubmit(values: SignupFormValues) {
    setFormError(null)
    try {
      const response = await signup({
        ...values,
        school: { ...values.school, email: values.school.email || undefined },
      })
      // Not logged in yet — provision() never calls Auth::login() (see
      // SignupResponse's docblock). Nothing to cache here; the redirect
      // below leaves the SPA entirely, and SignupCompletePage on the far
      // side of checkout is what actually establishes the session.
      setIsRedirecting(true)
      window.location.href = response.checkout_url
    } catch (error) {
      const apiError = error as ApiError
      if (apiError.errors) {
        let firstErrorStep: Step | null = null
        for (const [field, messages] of Object.entries(apiError.errors)) {
          setError(field as keyof SignupFormValues, { message: messages[0] })
          firstErrorStep ??= stepForField(field)
        }
        if (firstErrorStep) setStepIndex(STEPS.indexOf(firstErrorStep))
      } else {
        setFormError(apiError.message ?? 'Something went wrong. Please try again.')
      }
    }
  }

  return (
    <AuthLayout
      eyebrow="14-day free trial"
      title="Set up your school in three steps."
      description="No card charged until your trial ends. Every plan includes every module — the only difference is how much room your school needs."
      wide={step === 'plan'}
      footer={
        <p className="text-[13.5px] text-[var(--mk-ink-soft)]">Already set up? Sign in from your school's own address.</p>
      }
    >
      <div className="mb-7 flex items-center gap-2" role="progressbar" aria-valuenow={stepIndex + 1} aria-valuemin={1} aria-valuemax={STEPS.length}>
        {STEPS.map((s, i) => (
          <span
            key={s}
            className={cn(
              'h-1.5 flex-1 rounded-full transition-colors duration-300',
              i <= stepIndex ? 'bg-[var(--mk-marigold)]' : 'bg-[var(--mk-line-light)]'
            )}
          />
        ))}
      </div>
      <p className="mb-1 font-[var(--mk-font-mono)] text-[11px] uppercase tracking-[0.1em] text-[var(--mk-forest-deep)]">
        Step {stepIndex + 1} of {STEPS.length}
      </p>
      <h2 className="mb-6 text-[22px] font-medium text-[var(--mk-ink)]">{STEP_LABELS[step]}</h2>

      {formError && (
        <p role="alert" className="mb-4 rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">
          {formError}
        </p>
      )}

      <form onSubmit={handleSubmit(onSubmit)} noValidate>
        {step === 'school' && (
          <section className="flex flex-col gap-4">
            <FormField label="School name" htmlFor="school-name" error={errors.school?.name?.message} required>
              <Input id="school-name" invalid={!!errors.school?.name} {...register('school.name')} />
            </FormField>
            <FormField
              label="URL slug"
              htmlFor="school-short-name"
              error={errors.school?.short_name?.message}
              hint="Lowercase letters, numbers, and hyphens only — used to identify your school internally."
              required
            >
              <Input id="school-short-name" invalid={!!errors.school?.short_name} {...register('school.short_name')} />
            </FormField>
            <FormField label="School email" htmlFor="school-email" error={errors.school?.email?.message} hint="Optional">
              <Input id="school-email" type="email" invalid={!!errors.school?.email} {...register('school.email')} />
            </FormField>
            <FormField label="Phone" htmlFor="school-phone" hint="Optional">
              <Input id="school-phone" {...register('school.phone')} />
            </FormField>
          </section>
        )}

        {step === 'admin' && (
          <section className="flex flex-col gap-4">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <FormField label="First name" htmlFor="admin-first-name" error={errors.admin?.first_name?.message} required>
                <Input id="admin-first-name" invalid={!!errors.admin?.first_name} {...register('admin.first_name')} />
              </FormField>
              <FormField label="Last name" htmlFor="admin-last-name" error={errors.admin?.last_name?.message} required>
                <Input id="admin-last-name" invalid={!!errors.admin?.last_name} {...register('admin.last_name')} />
              </FormField>
            </div>
            <FormField label="Email" htmlFor="admin-email" error={errors.admin?.email?.message} required>
              <Input id="admin-email" type="email" autoComplete="email" invalid={!!errors.admin?.email} {...register('admin.email')} />
            </FormField>
            <FormField label="Password" htmlFor="admin-password" error={errors.admin?.password?.message} required>
              <Input id="admin-password" type="password" autoComplete="new-password" invalid={!!errors.admin?.password} {...register('admin.password')} />
            </FormField>
            <FormField
              label="Confirm password"
              htmlFor="admin-password-confirmation"
              error={errors.admin?.password_confirmation?.message}
              required
            >
              <Input
                id="admin-password-confirmation"
                type="password"
                autoComplete="new-password"
                invalid={!!errors.admin?.password_confirmation}
                {...register('admin.password_confirmation')}
              />
            </FormField>
          </section>
        )}

        {step === 'plan' && (
          <section className="flex flex-col gap-4">
            {errors.plan_id && <p className="text-sm text-destructive">{errors.plan_id.message}</p>}
            {plansLoading && <p className="text-sm text-muted-foreground">Loading plans…</p>}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
              {(plans ?? []).map((plan) => (
                <button
                  key={plan.id}
                  type="button"
                  onClick={() => setValue('plan_id', plan.id, { shouldValidate: true })}
                  className="text-left"
                >
                  <Card
                    className={cn(
                      'h-full transition-all',
                      planId === plan.id ? 'border-[var(--mk-marigold)] ring-1 ring-[var(--mk-marigold)]' : 'hover:border-[var(--mk-marigold)]/50'
                    )}
                  >
                    <CardContent className="flex flex-col gap-2 pt-6">
                      <div className="flex items-center justify-between">
                        <span className="font-semibold">{plan.name}</span>
                        {planId === plan.id && <Check className="h-4 w-4 text-[var(--mk-marigold)]" />}
                      </div>
                      <p className="text-2xl font-semibold">
                        ${(plan.price_cents / 100).toFixed(0)}
                        <span className="text-sm font-normal text-muted-foreground">/mo</span>
                      </p>
                      <p className="text-xs text-muted-foreground">{plan.trial_days}-day free trial</p>
                      {plan.description && <p className="text-sm text-muted-foreground">{plan.description}</p>}
                      <ul className="mt-2 flex flex-col gap-1 text-sm text-muted-foreground">
                        <li>{plan.max_students ? `Up to ${plan.max_students} students` : 'Unlimited students'}</li>
                        <li>{plan.max_staff ? `Up to ${plan.max_staff} staff` : 'Unlimited staff'}</li>
                      </ul>
                    </CardContent>
                  </Card>
                </button>
              ))}
            </div>
          </section>
        )}

        <div className="mt-8 flex items-center justify-between">
          {stepIndex > 0 ? (
            <Button type="button" variant="outline" onClick={goBack}>
              <ChevronLeft className="h-4 w-4" /> Back
            </Button>
          ) : (
            <span />
          )}

          {step === 'plan' ? (
            <Button type="submit" isLoading={isSubmitting || isRedirecting}>
              {isRedirecting ? 'Redirecting to checkout…' : 'Start trial'}
            </Button>
          ) : (
            <Button type="button" onClick={goNext}>
              Next <ChevronRight className="h-4 w-4" />
            </Button>
          )}
        </div>
      </form>
    </AuthLayout>
  )
}
