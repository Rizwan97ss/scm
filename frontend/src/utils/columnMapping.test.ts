import { describe, expect, it } from 'vitest'
import { guessColumnMapping } from './columnMapping'

describe('guessColumnMapping', () => {
  it('matches an exact (case/punctuation-insensitive) header', () => {
    expect(guessColumnMapping(['Name', 'CODE', 'description'], ['name', 'code', 'description'])).toEqual(['name', 'code', 'description'])
  })

  it('matches a human-friendly header via substring containment', () => {
    expect(guessColumnMapping(['Department Name', 'Dept Code'], ['name', 'code'])).toEqual(['name', 'code'])
  })

  it('matches when the canonical column is the longer, more specific side', () => {
    expect(guessColumnMapping(['code'], ['department_code'])).toEqual(['department_code'])
  })

  it('leaves an unrecognizable header unmapped', () => {
    expect(guessColumnMapping(['Random Column XYZ'], ['name', 'code'])).toEqual([null])
  })

  it('leaves a blank header unmapped without matching anything', () => {
    expect(guessColumnMapping(['', '   '], ['name'])).toEqual([null, null])
  })

  it('only assigns each canonical column once — a later duplicate match is left for the user to resolve', () => {
    expect(guessColumnMapping(['Code', 'Code (alt)'], ['code'])).toEqual(['code', null])
  })

  it('is order-independent across a full realistic header set', () => {
    expect(guessColumnMapping(['Dept Code', 'Department Name', 'Notes'], ['name', 'code', 'description'])).toEqual(['code', 'name', null])
  })
})
