import { useEffect, useState } from 'react';
import Topbar from '../components/layout/Topbar';
import api from '../services/api';

interface CallStats {
  calls_to_do: number;
  callbacks: number;
  calls_done: number;
  conversion_rate: string;
}

interface CallToday {
  id: number;
  time: string;
  who: string;
  source: string;
  sourceKey: string;
  prio: string;
  status: string;
  phone?: string;
}

interface Operator {
  id: number;
  name: string;
  calls: number;
  sales: number;
  avg: string;
}

export default function Calls() {
  const [stats, setStats] = useState<CallStats | null>(null);
  const [callsToday, setCallsToday] = useState<CallToday[]>([]);
  const [operators, setOperators] = useState<Operator[]>([]);
  const [loading, setLoading] = useState(true);
  const [showCallModal, setShowCallModal] = useState(false);
  const [selectedCall, setSelectedCall] = useState<CallToday | null>(null);
  const [callFormData, setCallFormData] = useState({
    outcome: 'successful',
    notes: '',
    next_action: '',
    callback_at: '',
    converted_to_opportunity: false,
    value: '',
    duration_seconds: '',
  });

  useEffect(() => {
    fetchData();
  }, []);

  const fetchData = async () => {
    try {
      setLoading(true);
      const [statsRes, todayRes, operatorsRes] = await Promise.all([
        api.get('/calls/stats'),
        api.get('/calls/today'),
        api.get('/calls/operators'),
      ]);

      setStats(statsRes.data);
      setCallsToday(todayRes.data || []);
      setOperators(operatorsRes.data || []);
    } catch (error) {
      console.error('Failed to fetch call center data:', error);
      setStats(null);
      setCallsToday([]);
      setOperators([]);
    } finally {
      setLoading(false);
    }
  };

  const handleCallClick = (call: CallToday) => {
    setSelectedCall(call);
    setShowCallModal(true);
    // Reset form
    setCallFormData({
      outcome: 'successful',
      notes: '',
      next_action: '',
      callback_at: '',
      converted_to_opportunity: false,
      value: '',
      duration_seconds: '',
    });
  };

  const handleCompleteCall = async () => {
    if (!selectedCall) return;

    try {
      const payload: any = {
        outcome: callFormData.outcome,
      };

      if (callFormData.notes) {
        payload.notes = callFormData.notes;
      }
      if (callFormData.next_action) {
        payload.next_action = callFormData.next_action;
      }
      if (callFormData.callback_at) {
        payload.callback_at = callFormData.callback_at;
      }
      if (callFormData.converted_to_opportunity) {
        payload.converted_to_opportunity = true;
        if (callFormData.value) {
          payload.value = parseFloat(callFormData.value);
        }
      }
      if (callFormData.duration_seconds) {
        payload.duration_seconds = parseInt(callFormData.duration_seconds);
      }

      await api.post(`/calls/${selectedCall.id}/complete`, payload);
      setShowCallModal(false);
      setSelectedCall(null);
      fetchData(); // Refresh data
    } catch (error) {
      console.error('Failed to complete call:', error);
      alert('Failed to complete call. Please try again.');
    }
  };

  const getPriorityColor = (prio: string) => {
    const p = prio.toLowerCase();
    if (p.includes('alta') || p.includes('urgent')) return 'bg-bad';
    if (p.includes('media') || p.includes('medium')) return 'bg-warn';
    return 'bg-ok';
  };

  const getPriorityDot = (prio: string) => {
    const color = getPriorityColor(prio);
    return <span className={`w-1.5 h-1.5 rounded-full ${color}`}></span>;
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-aqua-5"></div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <Topbar
        title="Call Center"
        subtitle="Manage calls, callbacks, and operator performance"
        actions={
          <>
            <button
              onClick={fetchData}
              className="px-4 py-2 text-sm border border-line rounded-xl hover:bg-aqua-1/30 transition-colors text-ink font-medium"
            >
              Refresh
            </button>
            <button className="px-4 py-2 text-sm border border-aqua-5/35 bg-gradient-to-r from-aqua-3/45 to-aqua-5/14 rounded-xl hover:shadow-lg hover:shadow-aqua-5/10 transition-all text-ink font-semibold">
              📞 Start Calling
            </button>
          </>
        }
      />

      {/* KPI Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="bg-white border border-line rounded-2xl p-5 shadow-sm">
          <h3 className="text-xs font-bold text-muted uppercase tracking-wider mb-3">Calls to Do</h3>
          <div className="flex items-end justify-between">
            <div className="text-3xl font-extrabold text-ink">{stats?.calls_to_do ?? 0}</div>
            <div className="text-sm font-semibold mb-1 text-muted">today</div>
          </div>
        </div>

        <div className="bg-white border border-line rounded-2xl p-5 shadow-sm">
          <h3 className="text-xs font-bold text-muted uppercase tracking-wider mb-3">Callbacks</h3>
          <div className="flex items-end justify-between">
            <div className="text-3xl font-extrabold text-ink">{stats?.callbacks ?? 0}</div>
            <div className="text-sm font-semibold mb-1 text-muted">within 24h</div>
          </div>
        </div>

        <div className="bg-white border border-line rounded-2xl p-5 shadow-sm">
          <h3 className="text-xs font-bold text-muted uppercase tracking-wider mb-3">Calls Done</h3>
          <div className="flex items-end justify-between">
            <div className="text-3xl font-extrabold text-ink">{stats?.calls_done ?? 0}</div>
            <div className="text-sm font-semibold mb-1 text-muted">today</div>
          </div>
        </div>

        <div className="bg-white border border-line rounded-2xl p-5 shadow-sm">
          <h3 className="text-xs font-bold text-muted uppercase tracking-wider mb-3">Conversion</h3>
          <div className="flex items-end justify-between">
            <div className="text-3xl font-extrabold text-ink">{stats?.conversion_rate ?? '0%'}</div>
            <div className="text-sm font-semibold mb-1 text-muted">sales/calls</div>
          </div>
        </div>
      </div>

      {/* Main Content Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Today's Calls Table */}
        <div className="lg:col-span-2 bg-white border border-line rounded-2xl p-6 shadow-sm">
          <h3 className="text-lg font-bold text-ink mb-4">Today's Calls</h3>
          {callsToday.length > 0 ? (
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead>
                  <tr className="border-b border-line">
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted">Time</th>
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted">Contact</th>
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted">Source</th>
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted">Priority</th>
                    <th className="text-right py-3 px-4 text-sm font-semibold text-muted">Action</th>
                  </tr>
                </thead>
                <tbody>
                  {callsToday.map((call) => (
                    <tr key={call.id} className="border-b border-line hover:bg-aqua-1/10 transition-colors">
                      <td className="py-3 px-4">
                        <span className="font-semibold text-ink">{call.time}</span>
                      </td>
                      <td className="py-3 px-4 text-ink">{call.who}</td>
                      <td className="py-3 px-4 text-muted">{call.source}</td>
                      <td className="py-3 px-4">
                        <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-aqua-1/20 text-xs font-medium">
                          {getPriorityDot(call.prio)}
                          {call.prio}
                        </span>
                      </td>
                      <td className="py-3 px-4 text-right">
                        <button
                          onClick={() => handleCallClick(call)}
                          className="px-3 py-1.5 text-xs border border-line rounded-lg hover:bg-aqua-1/30 transition-colors text-ink font-medium"
                        >
                          CALL
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <div className="text-center py-12 text-muted">
              <p>No calls scheduled for today.</p>
            </div>
          )}
        </div>

        {/* Operator Performance */}
        <div className="bg-white border border-line rounded-2xl p-6 shadow-sm">
          <h3 className="text-lg font-bold text-ink mb-4">Operator Performance</h3>
          {operators.length > 0 ? (
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead>
                  <tr className="border-b border-line">
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted">Operator</th>
                    <th className="text-center py-3 px-4 text-sm font-semibold text-muted">Calls</th>
                    <th className="text-center py-3 px-4 text-sm font-semibold text-muted">Sales</th>
                    <th className="text-center py-3 px-4 text-sm font-semibold text-muted">Avg</th>
                  </tr>
                </thead>
                <tbody>
                  {operators.map((op) => (
                    <tr key={op.id} className="border-b border-line hover:bg-aqua-1/10 transition-colors">
                      <td className="py-3 px-4">
                        <span className="font-semibold text-ink">{op.name}</span>
                      </td>
                      <td className="py-3 px-4 text-center text-ink">{op.calls}</td>
                      <td className="py-3 px-4 text-center text-ink">{op.sales}</td>
                      <td className="py-3 px-4 text-center text-muted">{op.avg} min</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <div className="text-center py-12 text-muted">
              <p>No operator data available.</p>
            </div>
          )}
        </div>
      </div>

      {/* Call Completion Modal */}
      {showCallModal && selectedCall && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded-2xl p-6 max-w-md w-full mx-4 max-h-[90vh] overflow-y-auto">
            <h2 className="text-xl font-bold text-ink mb-4">Complete Call</h2>
            <p className="text-muted mb-6">
              Call with <strong>{selectedCall.who}</strong> ({selectedCall.phone || 'No phone'})
            </p>

            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-ink mb-2">Outcome</label>
                <select
                  value={callFormData.outcome}
                  onChange={(e) => setCallFormData({ ...callFormData, outcome: e.target.value })}
                  className="w-full px-3 py-2 border border-line rounded-lg focus:outline-none focus:ring-2 focus:ring-aqua-5"
                >
                  <option value="successful">Successful</option>
                  <option value="no_answer">No Answer</option>
                  <option value="busy">Busy</option>
                  <option value="voicemail">Voicemail</option>
                  <option value="callback_requested">Callback Requested</option>
                  <option value="not_interested">Not Interested</option>
                  <option value="other">Other</option>
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium text-ink mb-2">Notes</label>
                <textarea
                  value={callFormData.notes}
                  onChange={(e) => setCallFormData({ ...callFormData, notes: e.target.value })}
                  rows={3}
                  className="w-full px-3 py-2 border border-line rounded-lg focus:outline-none focus:ring-2 focus:ring-aqua-5"
                  placeholder="Add call notes..."
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-ink mb-2">Next Action</label>
                <input
                  type="text"
                  value={callFormData.next_action}
                  onChange={(e) => setCallFormData({ ...callFormData, next_action: e.target.value })}
                  className="w-full px-3 py-2 border border-line rounded-lg focus:outline-none focus:ring-2 focus:ring-aqua-5"
                  placeholder="What to do next..."
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-ink mb-2">Callback Date/Time</label>
                <input
                  type="datetime-local"
                  value={callFormData.callback_at}
                  onChange={(e) => setCallFormData({ ...callFormData, callback_at: e.target.value })}
                  className="w-full px-3 py-2 border border-line rounded-lg focus:outline-none focus:ring-2 focus:ring-aqua-5"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-ink mb-2">Duration (seconds)</label>
                <input
                  type="number"
                  value={callFormData.duration_seconds}
                  onChange={(e) => setCallFormData({ ...callFormData, duration_seconds: e.target.value })}
                  className="w-full px-3 py-2 border border-line rounded-lg focus:outline-none focus:ring-2 focus:ring-aqua-5"
                  placeholder="Call duration in seconds"
                />
              </div>

              <div className="flex items-center gap-2">
                <input
                  type="checkbox"
                  id="converted"
                  checked={callFormData.converted_to_opportunity}
                  onChange={(e) => setCallFormData({ ...callFormData, converted_to_opportunity: e.target.checked })}
                  className="w-4 h-4 text-aqua-5 border-line rounded focus:ring-aqua-5"
                />
                <label htmlFor="converted" className="text-sm font-medium text-ink">
                  Converted to Opportunity
                </label>
              </div>

              {callFormData.converted_to_opportunity && (
                <div>
                  <label className="block text-sm font-medium text-ink mb-2">Value (€)</label>
                  <input
                    type="number"
                    step="0.01"
                    value={callFormData.value}
                    onChange={(e) => setCallFormData({ ...callFormData, value: e.target.value })}
                    className="w-full px-3 py-2 border border-line rounded-lg focus:outline-none focus:ring-2 focus:ring-aqua-5"
                    placeholder="0.00"
                  />
                </div>
              )}
            </div>

            <div className="flex gap-3 mt-6">
              <button
                onClick={() => {
                  setShowCallModal(false);
                  setSelectedCall(null);
                }}
                className="flex-1 px-4 py-2 border border-line rounded-xl hover:bg-aqua-1/30 transition-colors text-ink font-medium"
              >
                Cancel
              </button>
              <button
                onClick={handleCompleteCall}
                className="flex-1 px-4 py-2 bg-aqua-5 text-white rounded-xl hover:bg-aqua-4 transition-colors font-semibold"
              >
                Complete Call
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
