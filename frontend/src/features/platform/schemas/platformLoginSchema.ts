import { z } from 'zod'

export const platformLoginSchema = z.object({
  email: z.string().min(1, 'Email is required').email('Enter a valid email'),
  password: z.string().min(1, 'Password is required'),
  remember: z.boolean().optional(),
})

export type PlatformLoginFormValues = z.infer<typeof platformLoginSchema>
