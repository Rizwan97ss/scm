import { httpClient } from '@/api/client'
import { createCrudEndpoints } from './crudFactory'
import { apiFileUrl } from '@/utils/apiFileUrl'
import type { ApiResponse } from '@/types/api'
import type {
  CollectionSummary,
  CreditNote,
  FeeCategory,
  FeeCategoryPayload,
  FeeStatement,
  FeeStructure,
  FeeStructurePayload,
  GenerateInvoicesPayload,
  GenerateInvoicesResult,
  Invoice,
  InvoicePayload,
  IssueCreditNotePayload,
  OutstandingDues,
  Payment,
  RecordPaymentPayload,
  StudentFeeAssignment,
  StudentFeeAssignmentPayload,
} from '@/types/fees'

export const feeCategoriesApi = createCrudEndpoints<FeeCategory, FeeCategoryPayload>('fee-categories')

export const feeStructuresApi = {
  ...createCrudEndpoints<FeeStructure, FeeStructurePayload>('fee-structures'),
  generateInvoices: async (feeStructureId: number, payload: GenerateInvoicesPayload): Promise<GenerateInvoicesResult> => {
    const { data } = await httpClient.post<ApiResponse<GenerateInvoicesResult>>(`/fee-structures/${feeStructureId}/generate-invoices`, payload)
    return data.data
  },
}

export const studentFeeAssignmentsApi = createCrudEndpoints<StudentFeeAssignment, StudentFeeAssignmentPayload>('student-fee-assignments')

export const invoicesApi = {
  ...createCrudEndpoints<Invoice, InvoicePayload>('invoices'),
  void: async (invoiceId: number): Promise<Invoice> => {
    const { data } = await httpClient.post<ApiResponse<Invoice>>(`/invoices/${invoiceId}/void`)
    return data.data
  },
  recordPayment: async (invoiceId: number, payload: RecordPaymentPayload): Promise<Payment> => {
    const { data } = await httpClient.post<ApiResponse<Payment>>(`/invoices/${invoiceId}/payments`, payload)
    return data.data
  },
  issueCreditNote: async (invoiceId: number, payload: IssueCreditNotePayload): Promise<CreditNote> => {
    const { data } = await httpClient.post<ApiResponse<CreditNote>>(`/invoices/${invoiceId}/credit-notes`, payload)
    return data.data
  },
  statement: async (studentId: number): Promise<FeeStatement> => {
    const { data } = await httpClient.get<ApiResponse<FeeStatement>>(`/students/${studentId}/fee-statement`)
    return data.data
  },
}

export const paymentsApi = {
  list: async (params?: Record<string, unknown>) => {
    const { data } = await httpClient.get<ApiResponse<Payment[]> & { meta: unknown }>('/payments', { params })
    return data
  },
  receiptPdfUrl: (paymentId: number) => apiFileUrl(`/payments/${paymentId}/receipt/pdf`),
}

export const feeReportsApi = {
  collectionSummary: async (params?: { from_date?: string; to_date?: string }): Promise<CollectionSummary> => {
    const { data } = await httpClient.get<ApiResponse<CollectionSummary>>('/fee-reports/collection-summary', { params })
    return data.data
  },
  outstandingDues: async (): Promise<OutstandingDues> => {
    const { data } = await httpClient.get<ApiResponse<OutstandingDues>>('/fee-reports/outstanding-dues')
    return data.data
  },
}
