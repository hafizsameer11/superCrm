import { Outlet } from 'react-router-dom';
import Sidebar from './Sidebar';

export default function Layout() {
  return (
    <div className="min-h-screen grid grid-cols-[280px_1fr]">
      <Sidebar />
      <main className="flex-1 overflow-y-auto bg-gradient-to-br from-aqua-1/20 via-white to-white">
        <div className="p-6">
          <Outlet />
        </div>
      </main>
    </div>
  );
}
