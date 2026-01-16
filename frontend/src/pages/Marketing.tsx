import { useEffect, useState } from 'react';
import api from '../services/api';
import Topbar from '../components/layout/Topbar';

interface User {
  id: number;
  name: string;
  email: string;
}

interface Project {
  id: number;
  name: string;
}

interface Campaign {
  id: number;
  name: string;
  description: string | null;
  type: 'email' | 'sms' | 'social_media' | 'advertising' | 'content' | 'event' | 'other';
  status: 'draft' | 'scheduled' | 'active' | 'paused' | 'completed' | 'cancelled';
  start_date: string | null;
  end_date: string | null;
  scheduled_at: string | null;
  budget: number | null;
  spent: number;
  currency: string;
  sent_count: number;
  delivered_count: number;
  opened_count: number;
  clicked_count: number;
  converted_count: number;
  open_rate: number;
  click_rate: number;
  conversion_rate: number;
  roi: number | null;
  creator: User | null;
  project: Project | null;
  created_at: string;
  updated_at: string;
}

interface PaginatedResponse {
  data: Campaign[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

interface CampaignStats {
  total_campaigns: number;
  active_campaigns: number;
  total_sent: number;
  total_opened: number;
  total_clicked: number;
  total_converted: number;
  total_budget: number;
  total_spent: number;
  avg_open_rate: number;
  avg_click_rate: number;
  avg_conversion_rate: number;
}

export default function Marketing() {
  const [campaigns, setCampaigns] = useState<Campaign[]>([]);
  const [stats, setStats] = useState<CampaignStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [showModal, setShowModal] = useState(false);
  const [editingCampaign, setEditingCampaign] = useState<Campaign | null>(null);
  const [filters, setFilters] = useState({
    status: 'all',
    type: 'all',
    search: '',
  });
  const [pagination, setPagination] = useState({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
  });

  useEffect(() => {
    fetchCampaigns();
    fetchStats();
  }, [filters, pagination.current_page]);

  const fetchCampaigns = async () => {
    try {
      setLoading(true);
      const params: any = {
        page: pagination.current_page,
        per_page: pagination.per_page,
      };

      if (filters.status !== 'all') {
        params.status = filters.status;
      }

      if (filters.type !== 'all') {
        params.type = filters.type;
      }

      if (filters.search) {
        params.search = filters.search;
      }

      const response = await api.get<PaginatedResponse>('/campaigns', { params });
      setCampaigns(response.data.data || []);
      setPagination({
        current_page: response.data.current_page,
        last_page: response.data.last_page,
        per_page: response.data.per_page,
        total: response.data.total,
      });
    } catch (error) {
      console.error('Failed to fetch campaigns:', error);
      setCampaigns([]);
    } finally {
      setLoading(false);
    }
  };

  const fetchStats = async () => {
    try {
      const response = await api.get<CampaignStats>('/campaigns/stats');
      setStats(response.data);
    } catch (error) {
      console.error('Failed to fetch campaign stats:', error);
    }
  };

  const handleDelete = async (id: number) => {
    if (!confirm('Are you sure you want to delete this campaign?')) {
      return;
    }

    try {
      await api.delete(`/campaigns/${id}`);
      fetchCampaigns();
      fetchStats();
    } catch (error) {
      console.error('Failed to delete campaign:', error);
      alert('Failed to delete campaign');
    }
  };

  const getStatusBadge = (status: string) => {
    const styles = {
      draft: 'bg-muted/15 text-muted border-muted/30',
      scheduled: 'bg-aqua-1/30 text-aqua-5 border-aqua-5/30',
      active: 'bg-ok/15 text-ok border-ok/30',
      paused: 'bg-warn/15 text-warn border-warn/30',
      completed: 'bg-ok/15 text-ok border-ok/30',
      cancelled: 'bg-bad/15 text-bad border-bad/30',
    };
    return styles[status as keyof typeof styles] || styles.draft;
  };

  const getTypeBadge = (type: string) => {
    const typeNames = {
      email: 'Email',
      sms: 'SMS',
      social_media: 'Social Media',
      advertising: 'Advertising',
      content: 'Content',
      event: 'Event',
      other: 'Other',
    };
    return typeNames[type as keyof typeof typeNames] || type;
  };

  const formatCurrency = (amount: number | null, currency: string = 'EUR') => {
    if (amount === null) return '-';
    return new Intl.NumberFormat('it-IT', {
      style: 'currency',
      currency: currency,
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    }).format(amount);
  };

  const formatPercentage = (value: number) => {
    return `${value.toFixed(1)}%`;
  };

  if (loading && campaigns.length === 0) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-aqua-5"></div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <Topbar
        title="Marketing"
        subtitle="Campaigns, email marketing, and analytics"
        actions={
          <>
            <button
              onClick={fetchStats}
              className="px-4 py-2 text-sm border border-line rounded-xl hover:bg-aqua-1/30 transition-colors text-ink font-medium"
            >
              Analytics
            </button>
            <button
              onClick={() => {
                setEditingCampaign(null);
                setShowModal(true);
              }}
              className="px-4 py-2 text-sm border border-aqua-5/35 bg-gradient-to-r from-aqua-3/45 to-aqua-5/14 rounded-xl hover:shadow-lg hover:shadow-aqua-5/10 transition-all text-ink font-semibold"
            >
              ➕ New Campaign
            </button>
          </>
        }
      />

      {/* Stats Cards */}
      {stats && (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <div className="bg-white border border-line rounded-2xl p-5 shadow-sm">
            <h3 className="text-xs font-bold text-muted uppercase tracking-wider mb-3">Total Campaigns</h3>
            <div className="text-3xl font-extrabold text-ink">{stats.total_campaigns}</div>
            <div className="text-sm text-muted mt-1">{stats.active_campaigns} active</div>
          </div>

          <div className="bg-white border border-line rounded-2xl p-5 shadow-sm">
            <h3 className="text-xs font-bold text-muted uppercase tracking-wider mb-3">Total Sent</h3>
            <div className="text-3xl font-extrabold text-ink">{stats.total_sent.toLocaleString()}</div>
            <div className="text-sm text-muted mt-1">{formatPercentage(stats.avg_open_rate)} avg open rate</div>
          </div>

          <div className="bg-white border border-line rounded-2xl p-5 shadow-sm">
            <h3 className="text-xs font-bold text-muted uppercase tracking-wider mb-3">Total Clicks</h3>
            <div className="text-3xl font-extrabold text-ink">{stats.total_clicked.toLocaleString()}</div>
            <div className="text-sm text-muted mt-1">{formatPercentage(stats.avg_click_rate)} avg click rate</div>
          </div>

          <div className="bg-white border border-line rounded-2xl p-5 shadow-sm">
            <h3 className="text-xs font-bold text-muted uppercase tracking-wider mb-3">Total Budget</h3>
            <div className="text-3xl font-extrabold text-ink">{formatCurrency(stats.total_budget)}</div>
            <div className="text-sm text-muted mt-1">{formatCurrency(stats.total_spent)} spent</div>
          </div>
        </div>
      )}

      {/* Filters */}
      <div className="bg-white border border-line rounded-2xl p-4">
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label className="block text-xs font-semibold text-muted mb-2">Search</label>
            <input
              type="text"
              value={filters.search}
              onChange={(e) => setFilters({ ...filters, search: e.target.value })}
              placeholder="Search campaigns..."
              className="w-full px-3 py-2 border border-line rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-aqua-5/20"
            />
          </div>
          <div>
            <label className="block text-xs font-semibold text-muted mb-2">Status</label>
            <select
              value={filters.status}
              onChange={(e) => setFilters({ ...filters, status: e.target.value })}
              className="w-full px-3 py-2 border border-line rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-aqua-5/20"
            >
              <option value="all">All Status</option>
              <option value="draft">Draft</option>
              <option value="scheduled">Scheduled</option>
              <option value="active">Active</option>
              <option value="paused">Paused</option>
              <option value="completed">Completed</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
          <div>
            <label className="block text-xs font-semibold text-muted mb-2">Type</label>
            <select
              value={filters.type}
              onChange={(e) => setFilters({ ...filters, type: e.target.value })}
              className="w-full px-3 py-2 border border-line rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-aqua-5/20"
            >
              <option value="all">All Types</option>
              <option value="email">Email</option>
              <option value="sms">SMS</option>
              <option value="social_media">Social Media</option>
              <option value="advertising">Advertising</option>
              <option value="content">Content</option>
              <option value="event">Event</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div className="flex items-end">
            <button
              onClick={() => {
                setFilters({ status: 'all', type: 'all', search: '' });
                setPagination({ ...pagination, current_page: 1 });
              }}
              className="w-full px-4 py-2 text-sm border border-line rounded-xl hover:bg-aqua-1/30 transition-colors text-ink font-medium"
            >
              Clear Filters
            </button>
          </div>
        </div>
      </div>

      {/* Campaigns Table */}
      <div className="bg-white border border-line rounded-2xl overflow-hidden shadow-sm">
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead className="bg-aqua-1/30">
              <tr>
                <th className="px-4 py-3 text-left text-xs font-semibold text-muted uppercase">Campaign</th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-muted uppercase">Type</th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-muted uppercase">Status</th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-muted uppercase">Budget</th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-muted uppercase">Performance</th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-muted uppercase">Actions</th>
              </tr>
            </thead>
            <tbody>
              {campaigns.length === 0 ? (
                <tr>
                  <td colSpan={6} className="px-4 py-8 text-center text-muted">
                    No campaigns found. Create your first campaign to get started.
                  </td>
                </tr>
              ) : (
                campaigns.map((campaign) => (
                  <tr key={campaign.id} className="border-t border-line hover:bg-aqua-1/10 transition-colors">
                    <td className="px-4 py-3">
                      <div>
                        <div className="text-sm font-semibold text-ink">{campaign.name}</div>
                        {campaign.description && (
                          <div className="text-xs text-muted mt-1 line-clamp-1">{campaign.description}</div>
                        )}
                        {campaign.project && (
                          <div className="text-xs text-muted mt-1">Project: {campaign.project.name}</div>
                        )}
                      </div>
                    </td>
                    <td className="px-4 py-3">
                      <span className="px-2 py-1 rounded-full text-xs bg-aqua-1/30 text-aqua-5 border border-aqua-5/30">
                        {getTypeBadge(campaign.type)}
                      </span>
                    </td>
                    <td className="px-4 py-3">
                      <span className={`px-2 py-1 rounded-full text-xs border ${getStatusBadge(campaign.status)}`}>
                        {campaign.status}
                      </span>
                    </td>
                    <td className="px-4 py-3">
                      <div className="text-sm text-ink">
                        {formatCurrency(campaign.budget, campaign.currency)}
                      </div>
                      {campaign.spent > 0 && (
                        <div className="text-xs text-muted">Spent: {formatCurrency(campaign.spent, campaign.currency)}</div>
                      )}
                    </td>
                    <td className="px-4 py-3">
                      <div className="text-xs space-y-1">
                        <div>Sent: {campaign.sent_count.toLocaleString()}</div>
                        <div>Opens: {formatPercentage(campaign.open_rate)}</div>
                        <div>Clicks: {formatPercentage(campaign.click_rate)}</div>
                        {campaign.converted_count > 0 && (
                          <div>Conversions: {campaign.converted_count} ({formatPercentage(campaign.conversion_rate)})</div>
                        )}
                      </div>
                    </td>
                    <td className="px-4 py-3">
                      <div className="flex items-center gap-2">
                        <button
                          onClick={() => {
                            setEditingCampaign(campaign);
                            setShowModal(true);
                          }}
                          className="text-xs text-aqua-5 hover:text-aqua-4 font-medium"
                        >
                          Edit
                        </button>
                        <button
                          onClick={() => handleDelete(campaign.id)}
                          className="text-xs text-bad hover:text-bad/80 font-medium"
                        >
                          Delete
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        {pagination.last_page > 1 && (
          <div className="px-4 py-3 border-t border-line flex items-center justify-between">
            <div className="text-sm text-muted">
              Showing {((pagination.current_page - 1) * pagination.per_page) + 1} to{' '}
              {Math.min(pagination.current_page * pagination.per_page, pagination.total)} of {pagination.total} campaigns
            </div>
            <div className="flex gap-2">
              <button
                onClick={() => setPagination({ ...pagination, current_page: pagination.current_page - 1 })}
                disabled={pagination.current_page === 1}
                className="px-3 py-1 text-sm border border-line rounded-lg hover:bg-aqua-1/30 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Previous
              </button>
              <button
                onClick={() => setPagination({ ...pagination, current_page: pagination.current_page + 1 })}
                disabled={pagination.current_page === pagination.last_page}
                className="px-3 py-1 text-sm border border-line rounded-lg hover:bg-aqua-1/30 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Next
              </button>
            </div>
          </div>
        )}
      </div>

      {/* Create/Edit Modal - Simplified for now */}
      {showModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded-2xl p-6 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div className="flex justify-between items-center mb-4">
              <h2 className="text-xl font-bold text-ink">
                {editingCampaign ? 'Edit Campaign' : 'New Campaign'}
              </h2>
              <button
                onClick={() => {
                  setShowModal(false);
                  setEditingCampaign(null);
                }}
                className="text-muted hover:text-ink"
              >
                ✕
              </button>
            </div>
            <p className="text-muted mb-4">
              Campaign form will be implemented here. For now, you can create campaigns via API.
            </p>
            <div className="flex justify-end gap-2">
              <button
                onClick={() => {
                  setShowModal(false);
                  setEditingCampaign(null);
                }}
                className="px-4 py-2 text-sm border border-line rounded-xl hover:bg-aqua-1/30 transition-colors text-ink font-medium"
              >
                Close
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
