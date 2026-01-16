import { createBrowserRouter, Navigate } from 'react-router-dom';
import Login from './pages/Login';
import Dashboard from './pages/Dashboard';
import Sales from './pages/Sales';
import Leads from './pages/Leads';
import Calls from './pages/Calls';
import Support from './pages/Support';
import Marketing from './pages/Marketing';
import Companies from './pages/Companies';
import Projects from './pages/Projects';
import Customers from './pages/Customers';
import Users from './pages/Users';
import Settings from './pages/Settings';
import ProjectIframePage from './pages/ProjectIframePage';
import Layout from './components/layout/Layout';

const ProtectedRoute = ({ children }: { children: React.ReactNode }) => {
  const token = localStorage.getItem('auth_token');
  if (!token) {
    return <Navigate to="/login" replace />;
  }
  return <>{children}</>;
};

export const router = createBrowserRouter([
  {
    path: '/login',
    element: <Login />,
  },
  {
    path: '/',
    element: (
      <ProtectedRoute>
        <Layout />
      </ProtectedRoute>
    ),
    children: [
      {
        index: true,
        element: <Navigate to="/dashboard" replace />,
      },
      {
        path: 'dashboard',
        element: <Dashboard />,
      },
      {
        path: 'sales',
        element: <Sales />,
      },
      {
        path: 'leads',
        element: <Leads />,
      },
      {
        path: 'calls',
        element: <Calls />,
      },
      {
        path: 'support',
        element: <Support />,
      },
      {
        path: 'marketing',
        element: <Marketing />,
      },
      {
        path: 'customers',
        element: <Customers />,
      },
      {
        path: 'projects',
        element: <Projects />,
      },
      {
        path: 'projects/:projectId/iframe',
        element: <ProjectIframePage />,
      },
      {
        path: 'companies',
        element: <Companies />,
      },
      {
        path: 'users',
        element: <Users />,
      },
      {
        path: 'settings',
        element: <Settings />,
      },
    ],
  },
]);
