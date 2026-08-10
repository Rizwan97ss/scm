import { forwardRef, type InputHTMLAttributes } from 'react'
import { Input } from './Input'

export type DatePickerProps = Omit<InputHTMLAttributes<HTMLInputElement>, 'type'> & { invalid?: boolean }

/**
 * Native date input: full keyboard/screen-reader support and the platform's
 * own mobile date UI for free, without pulling in a calendar-popover library.
 */
export const DatePicker = forwardRef<HTMLInputElement, DatePickerProps>((props, ref) => {
  return <Input ref={ref} type="date" {...props} />
})
DatePicker.displayName = 'DatePicker'
