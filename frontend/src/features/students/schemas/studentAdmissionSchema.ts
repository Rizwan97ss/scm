import { z } from 'zod'

/**
 * Mirrors the backend's App\Rules\ValidName exactly (letters in any script,
 * digits, spaces, and the punctuation real names use: ' - & ( ) . , /) —
 * catching junk like "@@#@" here gives instant inline feedback instead of
 * a round-trip to the server for what the backend would reject anyway.
 */
const VALID_NAME_PATTERN = /^[\p{L}\p{N} .,''\-&()/]+$/u
const validName = (message: string) => z.string().regex(VALID_NAME_PATTERN, message)

export const guardianInputSchema = z.object({
  guardian_id: z.number().optional(),
  first_name: validName('First name may only contain letters, numbers, spaces, and \' - & ( ) . , /').min(1, 'Required'),
  last_name: validName('Last name may only contain letters, numbers, spaces, and \' - & ( ) . , /').min(1, 'Required'),
  email: z.string().email().optional().or(z.literal('')),
  phone: z.string().min(1, 'Required'),
  relationship_type: z.enum(['father', 'mother', 'guardian', 'other']),
  is_primary: z.boolean().optional(),
  can_pickup: z.boolean().optional(),
})

export const studentAdmissionSchema = z.object({
  first_name: validName('First name may only contain letters, numbers, spaces, and \' - & ( ) . , /').min(1, 'First name is required'),
  last_name: validName('Last name may only contain letters, numbers, spaces, and \' - & ( ) . , /').min(1, 'Last name is required'),
  gender: z.enum(['male', 'female', 'other']),
  date_of_birth: z.string().min(1, 'Date of birth is required'),
  blood_group: z.string().optional(),
  nationality: z.string().optional(),
  academic_year_id: z.number({ error: 'Academic year is required' }).min(1, 'Academic year is required'),
  current_grade_level_id: z.number().optional(),
  current_section_id: z.number().optional(),
  current_department_id: z.number().optional(),
  roll_number: z.string().optional(),
  admission_date: z.string().min(1, 'Admission date is required'),
  previous_school_name: z.string().optional(),
  emergency_contact_name: z.string().optional().refine((v) => !v || VALID_NAME_PATTERN.test(v), {
    message: 'Emergency contact name may only contain letters, numbers, spaces, and \' - & ( ) . , /',
  }),
  emergency_contact_phone: z.string().optional(),
  address_line1: z.string().optional(),
  city: z.string().optional(),
  guardians: z.array(guardianInputSchema),
})

export type StudentAdmissionFormValues = z.infer<typeof studentAdmissionSchema>
