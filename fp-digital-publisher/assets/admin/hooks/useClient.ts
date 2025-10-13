import { useState, useEffect } from 'react';

interface Client {
  id: number;
  name: string;
  slug: string;
  logo_url?: string;
  color: string;
  status: string;
  limits: {
    max_channels: number;
    max_posts_monthly: number;
    max_team_members: number;
    storage_bytes: number;
  };
}

export const useClient = () => {
  const [selectedClientId, setSelectedClientId] = useState<number | null>(() => {
    const saved = localStorage.getItem('fp_selected_client');
    return saved ? parseInt(saved) : null;
  });

  const [currentClient, setCurrentClient] = useState<Client | null>(null);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (selectedClientId) {
      fetchClient(selectedClientId);
    } else {
      setCurrentClient(null);
    }
  }, [selectedClientId]);

  const fetchClient = async (clientId: number) => {
    setLoading(true);
    try {
      const response = await fetch(`/wp-json/fp-publisher/v1/clients/${clientId}`);
      const data = await response.json();
      setCurrentClient(data);
    } catch (error) {
      console.error('Failed to fetch client:', error);
      setCurrentClient(null);
    } finally {
      setLoading(false);
    }
  };

  const selectClient = (clientId: number | null) => {
    setSelectedClientId(clientId);
    if (clientId) {
      localStorage.setItem('fp_selected_client', clientId.toString());
    } else {
      localStorage.removeItem('fp_selected_client');
    }
  };

  return {
    selectedClientId,
    currentClient,
    loading,
    selectClient,
  };
};

export const useClientJobs = (status?: string) => {
  const { selectedClientId } = useClient();
  const [jobs, setJobs] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchJobs();
  }, [selectedClientId, status]);

  const fetchJobs = async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams();
      if (selectedClientId) {
        params.set('client_id', selectedClientId.toString());
      }
      if (status) {
        params.set('status', status);
      }

      const response = await fetch(`/wp-json/fp-publisher/v1/jobs?${params}`);
      const data = await response.json();
      setJobs(data.jobs || []);
    } catch (error) {
      console.error('Failed to fetch jobs:', error);
      setJobs([]);
    } finally {
      setLoading(false);
    }
  };

  return { jobs, loading, refetch: fetchJobs };
};
