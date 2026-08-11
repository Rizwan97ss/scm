import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useNavigate } from 'react-router-dom'
import { useState } from 'react'
import { platformLoginSchema, type PlatformLoginFormValues } from '../schemas/platformLoginSchema'
import { usePlatformAuth } from '@/context/PlatformAuthContext'
import { Button, Checkbox, FormField, Input } from '@/components/ui'
import { routePaths } from '@/routes/routePaths'
import type { ApiError } from '@/api/client'

export function PlatformLoginForm() {
  const { login } = usePlatformAuth()
  const navigate = useNavigate()
  const [formError, setFormError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    watch,
    setValue,
    formState: { errors, isSubmitting },
  } = useForm<PlatformLoginFormValues>({
    resolver: zodResolver(platformLoginSchema),
    defaultValues: { email: '', password: '', remember: false },
  })

  async function onSubmit(values: PlatformLoginFormValues) {
    setFormError(null)
    try {
      await login(values)
      navigate(routePaths.platformSchools, { replace: true })
    } catch (error) {
      setFormError((error as ApiError).message ?? 'Unable to log in.')
    }
  }

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4" noValidate>
      {formError && (
        <p role="alert" className="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">
          {formError}
        </p>
      )}

      <FormField label="Email" htmlFor="platform-email" error={errors.email?.message} required>
        <Input id="platform-email" type="email" autoComplete="username" invalid={!!errors.email} {...register('email')} />
      </FormField>

      <FormField label="Password" htmlFor="platform-password" error={errors.password?.message} required>
        <Input id="platform-password" type="password" autoComplete="current-password" invalid={!!errors.password} {...register('password')} />
      </FormField>

      <label className="flex items-center gap-2 text-sm text-muted-foreground">
        <Checkbox checked={watch('remember')} onCheckedChange={(checked) => setValue('remember', checked)} aria-label="Remember me" />
        Remember me
      </label>

      <Button type="submit" isLoading={isSubmitting} className="mt-2">
        Log in
      </Button>
    </form>
  )
}
