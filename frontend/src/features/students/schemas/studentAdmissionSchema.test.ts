import { describe, expect, it } from 'vitest'
import { studentAdmissionSchema } from './studentAdmissionSchema'

const validBase = {
  first_name: 'Sam',
  last_name: 'Sample',
  gender: 'male' as const,
  date_of_birth: '2018-01-15',
  academic_year_id: 1,
  admission_date: '2026-01-01',
  guardians: [],
}

describe('studentAdmissionSchema', () => {
  it('accepts a minimal valid payload', () => {
    const result = studentAdmissionSchema.safeParse(validBase)
    expect(result.success).toBe(true)
  })

  it('rejects a missing first name', () => {
    const result = studentAdmissionSchema.safeParse({ ...validBase, first_name: '' })
    expect(result.success).toBe(false)
  })

  it('rejects a first name containing disallowed special characters', () => {
    const result = studentAdmissionSchema.safeParse({ ...validBase, first_name: '@@#@' })
    expect(result.success).toBe(false)
  })

  it("accepts a name with punctuation real names use (apostrophe, hyphen)", () => {
    const result = studentAdmissionSchema.safeParse({ ...validBase, first_name: "O'Brien", last_name: 'Smith-Jones' })
    expect(result.success).toBe(true)
  })

  it('rejects a guardian name containing disallowed special characters', () => {
    const result = studentAdmissionSchema.safeParse({
      ...validBase,
      guardians: [{ first_name: '<script>', last_name: 'One', phone: '555', relationship_type: 'mother' }],
    })
    expect(result.success).toBe(false)
  })

  it('rejects an emergency contact name containing disallowed special characters', () => {
    const result = studentAdmissionSchema.safeParse({ ...validBase, emergency_contact_name: '###' })
    expect(result.success).toBe(false)
  })

  it('rejects a missing academic year', () => {
    const { academic_year_id: _omit, ...withoutYear } = validBase
    const result = studentAdmissionSchema.safeParse(withoutYear)
    expect(result.success).toBe(false)
  })

  it('rejects a guardian with an invalid relationship type', () => {
    const result = studentAdmissionSchema.safeParse({
      ...validBase,
      guardians: [{ first_name: 'G', last_name: 'One', phone: '555', relationship_type: 'sibling' }],
    })
    expect(result.success).toBe(false)
  })

  it('accepts a guardian with a valid relationship type', () => {
    const result = studentAdmissionSchema.safeParse({
      ...validBase,
      guardians: [{ first_name: 'G', last_name: 'One', phone: '555', relationship_type: 'mother', is_primary: true }],
    })
    expect(result.success).toBe(true)
  })
})
