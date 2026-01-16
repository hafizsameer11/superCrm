import Topbar from '../components/layout/Topbar';

export default function Settings() {
  return (
    <div className="space-y-6">
      <Topbar
        title="Settings"
        subtitle="Configure your account and company settings"
      />

      <div className="bg-white border border-line rounded-2xl p-6">
        <p className="text-muted">Settings configuration coming soon...</p>
      </div>
    </div>
  );
}
