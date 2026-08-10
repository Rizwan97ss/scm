import { AppProviders } from './app/AppProviders'
import { AppRouter } from './routes/AppRouter'
import { ErrorBoundary } from './components/feedback/ErrorBoundary'
import { TooltipProvider } from './components/ui/Tooltip'

function App() {
  return (
    <ErrorBoundary>
      <AppProviders>
        <TooltipProvider>
          <AppRouter />
        </TooltipProvider>
      </AppProviders>
    </ErrorBoundary>
  )
}

export default App
