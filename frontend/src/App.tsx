import { Navigate, Route, Routes } from 'react-router-dom'

import { AppLayout } from './components/AppLayout'
import { Dashboard } from './pages/Dashboard'
import { EmployeeDetail } from './pages/EmployeeDetail'
import { EvaluateForm } from './pages/EvaluateForm'

export function App() {
  return (
    <AppLayout>
      <Routes>
        <Route path="/" element={<Dashboard />} />
        <Route path="/employees/:id" element={<EmployeeDetail />} />
        <Route path="/employees/:id/evaluate" element={<EvaluateForm />} />
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </AppLayout>
  )
}
