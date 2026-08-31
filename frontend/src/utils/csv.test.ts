import { describe, expect, it } from 'vitest'
import { rowsToCsvFile } from './csv'

async function readText(file: File): Promise<string> {
  return await file.text()
}

describe('rowsToCsvFile', () => {
  it('builds a header row plus one row per data row', async () => {
    const file = rowsToCsvFile(['name', 'code'], [['Mathematics', 'MATH']], 'test.csv')
    expect(await readText(file)).toBe('name,code\nMathematics,MATH')
  })

  it('quotes a field containing a comma', async () => {
    const file = rowsToCsvFile(['name', 'code'], [['Math, Science', 'MATSCI']], 'test.csv')
    expect(await readText(file)).toBe('name,code\n"Math, Science",MATSCI')
  })

  it('doubles internal quotes and wraps the field', async () => {
    const file = rowsToCsvFile(['name'], [['Say "hi"']], 'test.csv')
    expect(await readText(file)).toBe('name\n"Say ""hi"""')
  })

  it('quotes a field containing a newline', async () => {
    const file = rowsToCsvFile(['name'], [['Line one\nLine two']], 'test.csv')
    expect(await readText(file)).toBe('name\n"Line one\nLine two"')
  })

  it('fills a missing trailing cell with an empty string', async () => {
    const file = rowsToCsvFile(['name', 'code', 'description'], [['Math', 'MATH']], 'test.csv')
    expect(await readText(file)).toBe('name,code,description\nMath,MATH,')
  })

  it('produces the right MIME type and filename', () => {
    const file = rowsToCsvFile(['name'], [['Math']], 'grid-import.csv')
    expect(file.type).toBe('text/csv')
    expect(file.name).toBe('grid-import.csv')
  })
})
