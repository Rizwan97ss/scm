import { z } from 'zod'

export const signupSchema = z.object({
  school: z.object({
    name: z.string().min(1, 'School name is required'),
    short_name: z
      .string()
      .min(1, 'Required')
      .max(50, 'Keep it under 50 characters')
      .regex(/^[a-z0-9-]+$/, 'Lowercase letters, numbers, and hyphens only'),
    email: z.string().email('Enter a valid email').optional().or(z.literal('')),
    phone: z.string().optional(),
  }),
  admin: z
    .object({
      first_name: z.string().min(1, 'First name is required'),
      last_name: z.string().min(1, 'Last name is required'),
      email: z.string().min(1, 'Email is required').email('Enter a valid email'),
      password: z.string().min(8, 'At least 8 characters'),
      password_confirmation: z.string().min(1, 'Confirm your password'),
    })
    .refine((data) => data.password === data.password_confirmation, {
      message: 'Passwords do not match',
      path: ['password_confirmation'],
    }),
  plan_id: z.number({ error: 'Select a plan' }).min(1, 'Select a plan'),
})

export type SignupFormValues = z.infer<typeof signupSchema>

export const SIGNUP_STEP_FIELDS = {
  school: ['school.name', 'school.short_name', 'school.email', 'school.phone'],
  admin: ['admin.first_name', 'admin.last_name', 'admin.email', 'admin.password', 'admin.password_confirmation'],
  plan: ['plan_id'],
} as const
