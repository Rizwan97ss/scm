import { createCrudEndpoints } from './crudFactory'
import type { School, SchoolPayload } from '@/types/school'

export const schoolsApi = createCrudEndpoints<School, SchoolPayload>('schools')
