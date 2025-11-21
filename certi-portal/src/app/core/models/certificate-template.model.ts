export interface CertificateTemplate {
  id: number;
  name: string;
  description?: string;
  file_path?: string;
  file_url?: string;
  activity_type: 'course' | 'event' | 'other';
  status: 'active' | 'inactive';
  qr_position?: any;
  name_position?: any;
  certificates_count?: number;
  created_at: string;
  updated_at: string;
}