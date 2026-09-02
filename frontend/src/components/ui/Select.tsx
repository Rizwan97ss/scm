import * as RadixSelect from '@radix-ui/react-select'
import { Check, ChevronDown, Search } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { cn } from '@/utils/cn'

// Below this, a search box is just visual noise (gender, yes/no, status
// flags, ...) — above it (subjects, sections, students, rooms, ...) it's
// the difference between scrolling and typing.
const SEARCH_THRESHOLD = 6

// Radix's own keydown handler lives on Content and drives both typeahead
// (jump to the item starting with the typed letter) and arrow-key/Enter/
// Escape navigation. Letting ordinary character keys bubble from our
// search input would fight the input for those keystrokes; navigation/
// selection/close keys need to keep bubbling so arrow keys still move
// into the (now filtered) item list.
const NAV_KEYS = new Set(['ArrowDown', 'ArrowUp', 'Enter', 'Escape', 'Tab'])

export interface SelectOption {
  value: string
  label: string
  disabled?: boolean
}

export interface SelectProps {
  value?: string
  onValueChange?: (value: string) => void
  options: SelectOption[]
  placeholder?: string
  disabled?: boolean
  invalid?: boolean
  className?: string
  id?: string
  'aria-label'?: string
}

export function Select({ value, onValueChange, options, placeholder, disabled, invalid, className, id, ...rest }: SelectProps) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)
  const [search, setSearch] = useState('')
  const searchInputRef = useRef<HTMLInputElement>(null)
  const showSearch = options.length > SEARCH_THRESHOLD
  const filtered = search.trim()
    ? options.filter((o) => o.label.toLowerCase().includes(search.trim().toLowerCase()))
    : options

  // Select.Content has no onOpenAutoFocus (unlike Dialog/Popover) — it
  // always auto-focuses the selected/first item itself on open, with no
  // prop to override that. Stealing focus a frame later, once that's
  // already happened, is the only way to land it in the search box instead.
  useEffect(() => {
    if (!open || !showSearch) return
    const raf = requestAnimationFrame(() => searchInputRef.current?.focus())
    return () => cancelAnimationFrame(raf)
  }, [open, showSearch])

  return (
    <RadixSelect.Root
      value={value}
      onValueChange={onValueChange}
      disabled={disabled}
      open={open}
      onOpenChange={(next) => {
        setOpen(next)
        if (!next) setSearch('')
      }}
    >
      <RadixSelect.Trigger
        id={id}
        aria-label={rest['aria-label']}
        className={cn(
          'flex h-10 w-full items-center justify-between rounded-md border border-input bg-card px-3 py-2 text-sm text-foreground',
          'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1',
          'disabled:cursor-not-allowed disabled:opacity-50',
          invalid && 'border-destructive focus-visible:ring-destructive',
          className
        )}
      >
        <RadixSelect.Value placeholder={placeholder ?? t('common.select.placeholder')} />
        <RadixSelect.Icon>
          <ChevronDown className="h-4 w-4 opacity-60" />
        </RadixSelect.Icon>
      </RadixSelect.Trigger>
      <RadixSelect.Portal>
        <RadixSelect.Content
          className="z-50 flex max-h-72 flex-col overflow-hidden rounded-md border border-border bg-card text-foreground shadow-lg"
          position="popper"
          sideOffset={4}
        >
          {showSearch && (
            <div className="flex items-center gap-2 border-b border-border px-2.5 py-1.5">
              <Search className="h-3.5 w-3.5 shrink-0 opacity-50" />
              <input
                ref={searchInputRef}
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                onKeyDown={(e) => { if (!NAV_KEYS.has(e.key)) e.stopPropagation() }}
                placeholder={t('common.select.searchPlaceholder')}
                className="w-full bg-transparent text-sm outline-none placeholder:text-muted-foreground"
              />
            </div>
          )}
          <RadixSelect.Viewport className="overflow-y-auto p-1">
            {filtered.map((option) => (
              <RadixSelect.Item
                key={option.value}
                value={option.value}
                disabled={option.disabled}
                className={cn(
                  'relative flex cursor-pointer select-none items-center rounded-sm px-8 py-2 text-sm outline-none',
                  'data-[highlighted]:bg-muted data-[disabled]:pointer-events-none data-[disabled]:opacity-50'
                )}
              >
                <RadixSelect.ItemIndicator className="absolute start-2 inline-flex items-center">
                  <Check className="h-4 w-4" />
                </RadixSelect.ItemIndicator>
                <RadixSelect.ItemText>{option.label}</RadixSelect.ItemText>
              </RadixSelect.Item>
            ))}
            {showSearch && filtered.length === 0 && (
              <p className="px-2 py-4 text-center text-sm text-muted-foreground">{t('common.select.noResults')}</p>
            )}
          </RadixSelect.Viewport>
        </RadixSelect.Content>
      </RadixSelect.Portal>
    </RadixSelect.Root>
  )
}
