import { useEffect, useState } from 'react';
import Topbar from '../components/layout/Topbar';
import api from '../services/api';

interface Lead {
  id: number;
  name: string;
  email: string;
  phone: string;
  source: string;
  status: string;
  value?: number;
  created_at: string;
  assigned_to?: string;
}

export default function Leads() {
  const [leads, setLeads] = useState<Lead[]>([]);
  const [loading, setLoading] = useState(true);
  const [filters, setFilters] = useState({
    status: 'all',
    source: 'all',
    search: '',
  });
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [editingLead, setEditingLead] = useState<Lead | null>(null);
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    phone: '',
    source: '',
    status: 'cold',
    value: '',
    assigned_to: '',
  });

  // Debounce search
  useEffect(() => {
    const timer = setTimeout(() => {
      fetchLeads();
    }, 300);

    return () => clearTimeout(timer);
  }, [filters.search]);

  useEffect(() => {
    fetchLeads();
  }, [filters.status, filters.source]);

  const fetchLeads = async () => {
    try {
      setLoading(true);
      const params: any = {};
      
      if (filters.status !== 'all') {
        params.status = filters.status;
      }
      
      if (filters.source !== 'all') {
        params.source = filters.source;
      }
      
      if (filters.search) {
        params.search = filters.search;
      }

      const response = await api.get('/leads', { params });
      setLeads(response.data.data || []);
    } catch (error) {
      console.error('Failed to fetch leads:', error);
      setLeads([]);
    } finally {
      setLoading(false);
    }
  };

  const handleCreateLead = async () => {
    try {
      const payload: any = {
        name: formData.name,
        email: formData.email,
        phone: formData.phone,
        status: formData.status,
      };

      if (formData.source) {
        payload.source = formData.source;
      }

      if (formData.value) {
        payload.value = parseFloat(formData.value);
      }

      await api.post('/leads', payload);
      setShowCreateModal(false);
      resetForm();
      fetchLeads();
    } catch (error) {
      console.error('Failed to create lead:', error);
      alert('Failed to create lead. Please try again.');
    }
  };

  const handleUpdateLead = async () => {
    if (!editingLead) return;

    try {
      const payload: any = {};

      if (formData.name !== editingLead.name) {
        payload.name = formData.name;
      }
      if (formData.email !== editingLead.email) {
        payload.email = formData.email;
      }
      if (formData.phone !== editingLead.phone) {
        payload.phone = formData.phone;
      }
      if (formData.status !== editingLead.status) {
        payload.status = formData.status;
      }
      if (formData.source !== editingLead.source) {
        payload.source = formData.source;
      }
      if (formData.value !== String(editingLead.value || '')) {
        payload.value = formData.value ? parseFloat(formData.value) : null;
      }

      await api.put(`/leads/${editingLead.id}`, payload);
      setEditingLead(null);
      resetForm();
      fetchLeads();
    } catch (error) {
      console.error('Failed to update lead:', error);
      alert('Failed to update lead. Please try again.');
    }
  };

  const handleDeleteLead = async (leadId: number) => {
    if (!confirm('Are you sure you want to delete this lead?')) {
      return;
    }

    try {
      await api.delete(`/leads/${leadId}`);
      fetchLeads();
    } catch (error) {
      console.error('Failed to delete lead:', error);
      alert('Failed to delete lead. Please try again.');
    }
  };

  const resetForm = () => {
    setFormData({
      name: '',
      email: '',
      phone: '',
      source: '',
      status: 'cold',
      value: '',
      assigned_to: '',
    });
  };

  const openEditModal = (lead: Lead) => {
    setEditingLead(lead);
    setFormData({
      name: lead.name,
      email: lead.email,
      phone: lead.phone,
      source: lead.source,
      status: lead.status,
      value: lead.value ? String(lead.value) : '',
      assigned_to: lead.assigned_to || '',
    });
  };

  const clearFilters = () => {
    setFilters({
      status: 'all',
      source: 'all',
      search: '',
    });
  };

  // Get unique sources from leads for filter dropdown
  const uniqueSources = Array.from(new Set(leads.map(lead => lead.source).filter(Boolean))).sort();

  const getStatusBadge = (status: string) => {
    const styles = {
      hot: 'bg-bad/15 text-bad border-bad/30',
      warm: 'bg-warn/15 text-warn border-warn/30',
      cold: 'bg-muted/15 text-muted border-muted/30',
      converted: 'bg-ok/15 text-ok border-ok/30',
    };
    return styles[status as keyof typeof styles] || styles.cold;
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
        title="Leads Management"
        subtitle="Manage and track all your leads from different sources"
        actions={
          <>
            <button className="px-4 py-2 text-sm border border-line rounded-xl hover:bg-aqua-1/30 transition-colors text-ink font-medium">
              Export
            </button>
            <button 
              onClick={() => setShowCreateModal(true)}
              className="px-4 py-2 text-sm border border-aqua-5/35 bg-gradient-to-r from-aqua-3/45 to-aqua-5/14 rounded-xl hover:shadow-lg hover:shadow-aqua-5/10 transition-all text-ink font-semibold"
            >
              ➕ New Lead
            </button>
          </>
        }
      />

      {/* Filters */}
      <div className="bg-white border border-line rounded-2xl p-4">
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          <input
            type="text"
            placeholder="Search leads..."
            value={filters.search}
            onChange={(e) => setFilters({ ...filters, search: e.target.value })}
            className="px-4 py-2 border border-line rounded-xl focus:border-aqua-5 focus:ring-2 focus:ring-aqua-5/20 outline-none text-sm"
          />
          <select
            value={filters.status}
            onChange={(e) => setFilters({ ...filters, status: e.target.value })}
            className="px-4 py-2 border border-line rounded-xl focus:border-aqua-5 focus:ring-2 focus:ring-aqua-5/20 outline-none text-sm"
          >
            <option value="all">All Status</option>
            <option value="hot">Hot</option>
            <option value="warm">Warm</option>
            <option value="cold">Cold</option>
            <option value="converted">Converted</option>
          </select>
          <select
            value={filters.source}
            onChange={(e) => setFilters({ ...filters, source: e.target.value })}
            className="px-4 py-2 border border-line rounded-xl focus:border-aqua-5 focus:ring-2 focus:ring-aqua-5/20 outline-none text-sm"
          >
            <option value="all">All Sources</option>
            {uniqueSources.map((source) => (
              <option key={source} value={source}>
                {source}
              </option>
            ))}
          </select>
          <button 
            onClick={clearFilters}
            className="px-4 py-2 text-sm border border-line rounded-xl hover:bg-aqua-1/30 transition-colors text-ink font-medium"
          >
            Clear Filters
          </button>
        </div>
      </div>

      {/* Leads Table */}
      <div className="bg-white border border-line rounded-2xl shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead className="bg-aqua-1/30 border-b border-line">
              <tr>
                <th className="text-left text-xs font-bold text-muted uppercase py-3 px-4">Lead</th>
                <th className="text-left text-xs font-bold text-muted uppercase py-3 px-4">Contact</th>
                <th className="text-left text-xs font-bold text-muted uppercase py-3 px-4">Source</th>
                <th className="text-left text-xs font-bold text-muted uppercase py-3 px-4">Status</th>
                <th className="text-right text-xs font-bold text-muted uppercase py-3 px-4">Value</th>
                <th className="text-left text-xs font-bold text-muted uppercase py-3 px-4">Assigned To</th>
                <th className="text-left text-xs font-bold text-muted uppercase py-3 px-4">Created</th>
                <th className="text-right text-xs font-bold text-muted uppercase py-3 px-4">Actions</th>
              </tr>
            </thead>
            <tbody>
              {leads.map((lead) => (
                <tr key={lead.id} className="border-b border-line/50 hover:bg-aqua-1/10 transition-colors">
                  <td className="py-3 px-4">
                    <div className="font-semibold text-ink">{lead.name}</div>
                  </td>
                  <td className="py-3 px-4">
                    <div className="text-sm text-ink">{lead.email}</div>
                    <div className="text-xs text-muted">{lead.phone}</div>
                  </td>
                  <td className="py-3 px-4">
                    <span className="text-sm text-ink">{lead.source}</span>
                  </td>
                  <td className="py-3 px-4">
                    <span className={`text-xs px-2 py-1 rounded-full border font-medium ${getStatusBadge(lead.status)}`}>
                      {lead.status}
                    </span>
                  </td>
                  <td className="py-3 px-4 text-right">
                    {lead.value ? <span className="font-semibold text-ink">€ {lead.value.toLocaleString()}</span> : '-'}
                  </td>
                  <td className="py-3 px-4">
                    <span className="text-sm text-muted">{lead.assigned_to || 'Unassigned'}</span>
                  </td>
                  <td className="py-3 px-4">
                    <span className="text-sm text-muted">{new Date(lead.created_at).toLocaleDateString()}</span>
                  </td>
                  <td className="py-3 px-4">
                    <div className="flex items-center justify-end gap-2">
                      <button 
                        onClick={() => openEditModal(lead)}
                        className="p-1.5 hover:bg-aqua-1 rounded-lg transition-colors" 
                        title="Edit"
                      >
                        ✏️
                      </button>
                      <button 
                        onClick={() => handleDeleteLead(lead.id)}
                        className="p-1.5 hover:bg-aqua-1 rounded-lg transition-colors text-red-500" 
                        title="Delete"
                      >
                        🗑️
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        {leads.length === 0 && !loading && (
          <div className="p-8 text-center text-muted">
            No leads found. Create your first lead to get started!
          </div>
        )}
      </div>

      {/* Create/Edit Modal */}
      {(showCreateModal || editingLead) && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded-2xl p-6 w-full max-w-md mx-4">
            <h2 className="text-xl font-bold text-ink mb-4">
              {editingLead ? 'Edit Lead' : 'Create New Lead'}
            </h2>
            
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-ink mb-1">Name *</label>
                <input
                  type="text"
                  value={formData.name}
                  onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                  className="w-full px-4 py-2 border border-line rounded-xl focus:border-aqua-5 focus:ring-2 focus:ring-aqua-5/20 outline-none"
                  required
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-ink mb-1">Email *</label>
                <input
                  type="email"
                  value={formData.email}
                  onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                  className="w-full px-4 py-2 border border-line rounded-xl focus:border-aqua-5 focus:ring-2 focus:ring-aqua-5/20 outline-none"
                  required
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-ink mb-1">Phone *</label>
                <input
                  type="tel"
                  value={formData.phone}
                  onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
                  className="w-full px-4 py-2 border border-line rounded-xl focus:border-aqua-5 focus:ring-2 focus:ring-aqua-5/20 outline-none"
                  required
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-ink mb-1">Source</label>
                <input
                  type="text"
                  value={formData.source}
                  onChange={(e) => setFormData({ ...formData, source: e.target.value })}
                  placeholder="e.g., OptyShop, Aziende TG Calabria"
                  className="w-full px-4 py-2 border border-line rounded-xl focus:border-aqua-5 focus:ring-2 focus:ring-aqua-5/20 outline-none"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-ink mb-1">Status</label>
                <select
                  value={formData.status}
                  onChange={(e) => setFormData({ ...formData, status: e.target.value })}
                  className="w-full px-4 py-2 border border-line rounded-xl focus:border-aqua-5 focus:ring-2 focus:ring-aqua-5/20 outline-none"
                >
                  <option value="hot">Hot</option>
                  <option value="warm">Warm</option>
                  <option value="cold">Cold</option>
                  <option value="converted">Converted</option>
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium text-ink mb-1">Value (€)</label>
                <input
                  type="number"
                  value={formData.value}
                  onChange={(e) => setFormData({ ...formData, value: e.target.value })}
                  placeholder="0.00"
                  step="0.01"
                  className="w-full px-4 py-2 border border-line rounded-xl focus:border-aqua-5 focus:ring-2 focus:ring-aqua-5/20 outline-none"
                />
              </div>
            </div>

            <div className="flex gap-3 mt-6">
              <button
                onClick={() => {
                  setShowCreateModal(false);
                  setEditingLead(null);
                  resetForm();
                }}
                className="flex-1 px-4 py-2 border border-line rounded-xl hover:bg-aqua-1/30 transition-colors text-ink font-medium"
              >
                Cancel
              </button>
              <button
                onClick={editingLead ? handleUpdateLead : handleCreateLead}
                className="flex-1 px-4 py-2 bg-aqua-5 text-white rounded-xl hover:bg-aqua-4 transition-colors font-semibold"
              >
                {editingLead ? 'Update' : 'Create'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
