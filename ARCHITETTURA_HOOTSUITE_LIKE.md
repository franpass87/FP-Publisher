# FP Publisher: Social Media Management Platform (Hootsuite-like)
**Architettura Completa**  
**Data**: 2025-10-13  
**Target**: Social Media Managers, Agenzie, Brand

---

## 🎯 Visione

Trasformare **FP Digital Publisher** in una **piattaforma completa di social media management** stile Hootsuite, integrata nativamente in WordPress.

### Funzionalità Core (Hootsuite-like)

1. ✅ **Dashboard Unificata** - Overview tutti i canali
2. ✅ **Composer Universale** - Crea post per multi-canale
3. ✅ **Calendario Editoriale** - Visual planning mensile/settimanale
4. ✅ **Content Library** - Repository asset riutilizzabili
5. ✅ **Analytics Dashboard** - Metriche cross-platform
6. ✅ **Team Collaboration** - Ruoli, approvazioni, commenti
7. ✅ **Bulk Scheduler** - Carica CSV, schedule automatico
8. ✅ **Streams Monitor** - Feed real-time dei canali
9. ✅ **Auto-Publishing** - Queue intelligente
10. ✅ **Reports** - Export PDF/Excel analytics

---

## 📐 Architettura UI/UX

### Layout Principale

```
┌─────────────────────────────────────────────────────────────────────┐
│  [Logo FP Publisher]    Dashboard    Composer    Calendar    ...    │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  SIDEBAR                      MAIN CONTENT AREA                      │
│  ┌──────────────┐      ┌────────────────────────────────────┐      │
│  │              │      │                                      │      │
│  │ 🏠 Dashboard │      │                                      │      │
│  │ ✍️  Composer │      │         DYNAMIC CONTENT              │      │
│  │ 📅 Calendar  │      │         (React Components)           │      │
│  │ 📊 Analytics │      │                                      │      │
│  │ 📚 Library   │      │                                      │      │
│  │ 👥 Team      │      │                                      │      │
│  │ ⚙️  Settings │      │                                      │      │
│  │              │      │                                      │      │
│  │ ─────────── │      └────────────────────────────────────┘      │
│  │ CHANNELS:    │                                                   │
│  │              │                                                   │
│  │ 📘 Facebook  │                                                   │
│  │ 📷 Instagram │                                                   │
│  │ 🎬 YouTube   │                                                   │
│  │ 🎵 TikTok    │                                                   │
│  │ 📍 Google MB │                                                   │
│  │ 📝 WordPress │                                                   │
│  │              │                                                   │
│  │ + Aggiungi   │                                                   │
│  └──────────────┘                                                   │
│                                                                       │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 1. 🏠 Dashboard Unificata

### Layout Dashboard

```
┌─────────────────────────────────────────────────────────────────────┐
│  Dashboard Overview                          Cliente: [Tutti ▼]      │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  📊 METRICHE QUICK                                                   │
│  ┌─────────────┬─────────────┬─────────────┬─────────────┐         │
│  │ Pubblicati  │  In Coda    │  Falliti    │  Scheduled  │         │
│  │    47       │     12      │     2       │     23      │         │
│  │  +15% ↗     │   -5% ↘     │   -50% ↘    │  +30% ↗     │         │
│  └─────────────┴─────────────┴─────────────┴─────────────┘         │
│                                                                       │
│  📈 ENGAGEMENT OVERVIEW (Ultimi 7 giorni)                           │
│  ┌───────────────────────────────────────────────────────────┐     │
│  │  [Grafico linee: Likes, Comments, Shares per canale]     │     │
│  │                                                            │     │
│  │     📘 Facebook  ────                                      │     │
│  │     📷 Instagram ····                                      │     │
│  │     🎬 YouTube   ‒‒‒                                      │     │
│  └───────────────────────────────────────────────────────────┘     │
│                                                                       │
│  🔥 TOP PERFORMING POSTS                                            │
│  ┌───────────────────────────────────────────────────────────┐     │
│  │  1. [Thumbnail] "Tutorial WordPress..."                   │     │
│  │     📷 Instagram  •  1.2K likes  •  347 comments          │     │
│  │                                                            │     │
│  │  2. [Thumbnail] "Nuova funzionalità..."                   │     │
│  │     📘 Facebook  •  856 reactions  •  124 shares          │     │
│  │                                                            │     │
│  │  3. [Thumbnail] "Behind the scenes..."                    │     │
│  │     🎬 YouTube  •  5.3K views  •  89% retention           │     │
│  └───────────────────────────────────────────────────────────┘     │
│                                                                       │
│  ⏰ PROSSIMI SCHEDULED                                              │
│  ┌───────────────────────────────────────────────────────────┐     │
│  │  Oggi 18:00     📷 "Tutorial editing..."  [Modifica] [❌]  │     │
│  │  Domani 12:00   📘 "Nuova promozione..."  [Modifica] [❌]  │     │
│  │  15/10 14:00    🎬 "Video recensione..."  [Modifica] [❌]  │     │
│  └───────────────────────────────────────────────────────────┘     │
│                                                                       │
│  ⚠️  ALERTS & NOTIFICATIONS                                         │
│  ┌───────────────────────────────────────────────────────────┐     │
│  │  🔴 2 pubblicazioni fallite richiedono attenzione          │     │
│  │  🟡 Token Instagram scade tra 5 giorni                     │     │
│  │  🟢 5 approvazioni pendenti                                │     │
│  └───────────────────────────────────────────────────────────┘     │
│                                                                       │
└─────────────────────────────────────────────────────────────────────┘
```

### Implementazione Dashboard

```typescript
// fp-digital-publisher/assets/admin/components/Dashboard.tsx

import React, { useEffect, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Card, Grid, LineChart, StatCard } from './ui';

interface DashboardStats {
  published: { total: number; change: number };
  queued: { total: number; change: number };
  failed: { total: number; change: number };
  scheduled: { total: number; change: number };
}

interface EngagementData {
  date: string;
  facebook: number;
  instagram: number;
  youtube: number;
  tiktok: number;
}

export const Dashboard: React.FC = () => {
  const { data: stats } = useQuery<DashboardStats>({
    queryKey: ['dashboard-stats'],
    queryFn: () => fetch('/wp-json/fp-publisher/v1/dashboard/stats').then(r => r.json())
  });

  const { data: engagement } = useQuery<EngagementData[]>({
    queryKey: ['engagement-overview'],
    queryFn: () => fetch('/wp-json/fp-publisher/v1/analytics/engagement?days=7').then(r => r.json())
  });

  const { data: topPosts } = useQuery({
    queryKey: ['top-posts'],
    queryFn: () => fetch('/wp-json/fp-publisher/v1/analytics/top-posts?limit=5').then(r => r.json())
  });

  const { data: upcoming } = useQuery({
    queryKey: ['upcoming-scheduled'],
    queryFn: () => fetch('/wp-json/fp-publisher/v1/jobs/scheduled?limit=10').then(r => r.json())
  });

  return (
    <div className="fp-dashboard">
      {/* Stats Cards */}
      <Grid cols={4} gap={4}>
        <StatCard
          label="Pubblicati"
          value={stats?.published.total ?? 0}
          change={stats?.published.change ?? 0}
          icon="📊"
        />
        <StatCard
          label="In Coda"
          value={stats?.queued.total ?? 0}
          change={stats?.queued.change ?? 0}
          icon="⏳"
        />
        <StatCard
          label="Falliti"
          value={stats?.failed.total ?? 0}
          change={stats?.failed.change ?? 0}
          icon="⚠️"
        />
        <StatCard
          label="Scheduled"
          value={stats?.scheduled.total ?? 0}
          change={stats?.scheduled.change ?? 0}
          icon="📅"
        />
      </Grid>

      {/* Engagement Chart */}
      <Card className="mt-6">
        <h2>Engagement Overview (7 giorni)</h2>
        <LineChart
          data={engagement ?? []}
          lines={[
            { key: 'facebook', label: 'Facebook', color: '#1877F2' },
            { key: 'instagram', label: 'Instagram', color: '#E4405F' },
            { key: 'youtube', label: 'YouTube', color: '#FF0000' },
            { key: 'tiktok', label: 'TikTok', color: '#000000' }
          ]}
        />
      </Card>

      {/* Top Posts */}
      <Card className="mt-6">
        <h2>🔥 Top Performing Posts</h2>
        <TopPostsList posts={topPosts ?? []} />
      </Card>

      {/* Upcoming Scheduled */}
      <Card className="mt-6">
        <h2>⏰ Prossimi Scheduled</h2>
        <ScheduledJobsList jobs={upcoming ?? []} />
      </Card>

      {/* Alerts */}
      <AlertsPanel />
    </div>
  );
};
```

---

## 2. ✍️ Composer Universale

### UI Composer

```
┌─────────────────────────────────────────────────────────────────────┐
│  ✍️  Crea Nuovo Post                                   [Salva Bozza] │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  SELEZIONA CANALI:                                                   │
│  ┌───────────────────────────────────────────────────────────┐     │
│  │ [✓] 📘 Facebook    [✓] 📷 Instagram    [✓] 🎬 YouTube     │     │
│  │ [ ] 🎵 TikTok      [ ] 📍 Google MB    [ ] 📝 WordPress   │     │
│  └───────────────────────────────────────────────────────────┘     │
│                                                                       │
│  MEDIA:                                                              │
│  ┌───────────────────────────────────────────────────────────┐     │
│  │  [Drag & Drop Area]                                        │     │
│  │                                                            │     │
│  │  📹 video-tutorial.mp4                                     │     │
│  │  ├─ Formato: 9:16 verticale (1080x1920)                   │     │
│  │  ├─ Durata: 45s                                           │     │
│  │  └─ Compatibile con: IG Reels ⭐⭐⭐, TikTok ⭐⭐⭐       │     │
│  │                                                            │     │
│  │  [+ Aggiungi Media]  [Libreria]                           │     │
│  └───────────────────────────────────────────────────────────┘     │
│                                                                       │
│  TESTO:                                                              │
│  ┌───────────────────────────────────────────────────────────┐     │
│  │  Tutorial veloce per creare post social! 🚀               │     │
│  │                                                            │     │
│  │  #tutorial #socialmedia #wordpress                        │     │
│  │                                                            │     │
│  │  [280/2200 caratteri]                                     │     │
│  │                                                            │     │
│  │  💡 Suggerimenti AI:                                       │     │
│  │  • Aggiungi emoji per +23% engagement                     │     │
│  │  • Best time to post: Oggi 18:00-20:00                    │     │
│  │  • Hashtag trending: #contentcreator                      │     │
│  └───────────────────────────────────────────────────────────┘     │
│                                                                       │
│  PERSONALIZZAZIONI PER CANALE:                                      │
│  ┌───────────────────────────────────────────────────────────┐     │
│  │  Tabs: [ Facebook ] [ Instagram ] [ YouTube ]             │     │
│  │                                                            │     │
│  │  📷 Instagram Specifico:                                   │     │
│  │  ├─ Caption: [Usa testo comune ✓]                         │     │
│  │  ├─ First Comment: Link in bio! 👆                         │     │
│  │  ├─ Story: [ ] Pubblica anche come Story                  │     │
│  │  └─ Reels: [✓] Pubblica come Reel                         │     │
│  └───────────────────────────────────────────────────────────┘     │
│                                                                       │
│  PROGRAMMAZIONE:                                                     │
│  ┌───────────────────────────────────────────────────────────┐     │
│  │  [●] Pubblica ora                                          │     │
│  │  [ ] Programma per: [15/10/2025] [18:00]                  │     │
│  │  [ ] Aggiungi a coda: Best time automatico                │     │
│  │                                                            │     │
│  │  Per canale:                                               │     │
│  │  • Facebook:  Oggi 18:00                                   │     │
│  │  • Instagram: Oggi 18:00                                   │     │
│  │  • YouTube:   Oggi 18:05 (delay 5min)                     │     │
│  └───────────────────────────────────────────────────────────┘     │
│                                                                       │
│  [← Annulla]  [👁️ Anteprima]  [✅ Pubblica su 3 canali]             │
│                                                                       │
└─────────────────────────────────────────────────────────────────────┘
```

### Implementazione Composer

```typescript
// fp-digital-publisher/assets/admin/components/Composer/UniversalComposer.tsx

import React, { useState } from 'react';
import { ChannelSelector } from './ChannelSelector';
import { MediaUploader } from './MediaUploader';
import { TextEditor } from './TextEditor';
import { ChannelCustomization } from './ChannelCustomization';
import { SchedulingOptions } from './SchedulingOptions';
import { PreviewModal } from './PreviewModal';

interface ComposerState {
  selectedChannels: string[];
  media: MediaFile[];
  text: string;
  channelCustomizations: Record<string, any>;
  scheduling: ScheduleConfig;
}

export const UniversalComposer: React.FC = () => {
  const [state, setState] = useState<ComposerState>({
    selectedChannels: [],
    media: [],
    text: '',
    channelCustomizations: {},
    scheduling: { type: 'now' }
  });

  const [showPreview, setShowPreview] = useState(false);

  const handleMediaUpload = async (files: File[]) => {
    // Upload e rileva formato
    const uploadedMedia = await uploadMedia(files);
    
    // Rileva formato e suggerisci canali
    const format = await detectFormat(uploadedMedia[0]);
    const suggested = await getCompatibleChannels(format);
    
    setState(prev => ({
      ...prev,
      media: uploadedMedia,
      suggestedChannels: suggested
    }));
  };

  const handlePublish = async () => {
    const payload = {
      channels: state.selectedChannels,
      media: state.media,
      text: state.text,
      customizations: state.channelCustomizations,
      scheduling: state.scheduling
    };

    const response = await fetch('/wp-json/fp-publisher/v1/publish/multi-channel', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    const result = await response.json();
    
    // Redirect a dashboard con notifica successo
    window.location.href = '/wp-admin/admin.php?page=fp-publisher&success=published';
  };

  return (
    <div className="fp-composer">
      <div className="composer-header">
        <h1>✍️ Crea Nuovo Post</h1>
        <button onClick={() => saveDraft()}>Salva Bozza</button>
      </div>

      <ChannelSelector
        selected={state.selectedChannels}
        suggested={state.suggestedChannels}
        onChange={(channels) => setState(prev => ({ ...prev, selectedChannels: channels }))}
      />

      <MediaUploader
        media={state.media}
        onUpload={handleMediaUpload}
        onRemove={(id) => setState(prev => ({
          ...prev,
          media: prev.media.filter(m => m.id !== id)
        }))}
      />

      <TextEditor
        value={state.text}
        onChange={(text) => setState(prev => ({ ...prev, text }))}
        maxLength={2200}
        suggestions={getAISuggestions(state.text, state.selectedChannels)}
      />

      <ChannelCustomization
        channels={state.selectedChannels}
        customizations={state.channelCustomizations}
        onChange={(channel, config) => setState(prev => ({
          ...prev,
          channelCustomizations: { ...prev.channelCustomizations, [channel]: config }
        }))}
      />

      <SchedulingOptions
        config={state.scheduling}
        onChange={(scheduling) => setState(prev => ({ ...prev, scheduling }))}
      />

      <div className="composer-actions">
        <button onClick={() => window.history.back()}>← Annulla</button>
        <button onClick={() => setShowPreview(true)}>👁️ Anteprima</button>
        <button 
          className="primary"
          onClick={handlePublish}
          disabled={state.selectedChannels.length === 0}
        >
          ✅ Pubblica su {state.selectedChannels.length} canali
        </button>
      </div>

      {showPreview && (
        <PreviewModal
          state={state}
          onClose={() => setShowPreview(false)}
        />
      )}
    </div>
  );
};
```

---

## 3. 📅 Calendario Editoriale

### UI Calendario

```
┌─────────────────────────────────────────────────────────────────────┐
│  📅 Calendario Editoriale            Ottobre 2025    [< Oggi >]      │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  Vista: [○ Giorno] [● Settimana] [○ Mese]    Filtra: [Tutti ▼]     │
│                                                                       │
│  ┌───────┬───────┬───────┬───────┬───────┬───────┬───────┐         │
│  │ Lun   │ Mar   │ Mer   │ Gio   │ Ven   │ Sab   │ Dom   │         │
│  │  13   │  14   │  15   │  16   │  17   │  18   │  19   │         │
│  ├───────┼───────┼───────┼───────┼───────┼───────┼───────┤         │
│  │       │       │ 📷    │       │ 📘    │       │       │         │
│  │       │       │ 18:00 │       │ 12:00 │       │       │         │
│  │       │       │ IG    │       │ FB    │       │       │         │
│  │       │       │ Reel  │       │ Post  │       │       │         │
│  │       │       │       │       │       │       │       │         │
│  │       │       │ 🎬    │       │ 📷    │       │       │         │
│  │       │       │ 18:05 │       │ 18:00 │       │       │         │
│  │       │       │ YT    │       │ IG    │       │       │         │
│  │       │       │ Short │       │ Story │       │       │         │
│  ├───────┼───────┼───────┼───────┼───────┼───────┼───────┤         │
│  │       │       │       │       │       │       │       │         │
│  │       │       │ [+ ]  │       │       │       │       │         │
│  │       │       │       │       │       │       │       │         │
│  └───────┴───────┴───────┴───────┴───────┴───────┴───────┘         │
│                                                                       │
│  DETTAGLI EVENTO SELEZIONATO:                                       │
│  ┌───────────────────────────────────────────────────────────┐     │
│  │  Mer 15 Ott, 18:00 - Instagram Reel                       │     │
│  │  ┌─────────────────────────────────────────────────┐     │     │
│  │  │ [Preview Thumbnail]                              │     │     │
│  │  │ "Tutorial veloce WordPress..."                   │     │     │
│  │  │                                                   │     │     │
│  │  │ 📊 Status: Scheduled ⏰                           │     │     │
│  │  │ 👤 Creato da: Mario Rossi                        │     │     │
│  │  │ 🏷️  Tag: #tutorial #wordpress                     │     │     │
│  │  └─────────────────────────────────────────────────┘     │     │
│  │                                                            │     │
│  │  [✏️ Modifica] [🗑️ Elimina] [📋 Duplica] [👁️ Anteprima]   │     │
│  └───────────────────────────────────────────────────────────┘     │
│                                                                       │
└─────────────────────────────────────────────────────────────────────┘
```

### Implementazione Calendario

```typescript
// fp-digital-publisher/assets/admin/components/Calendar/EditorialCalendar.tsx

import React, { useState } from 'react';
import FullCalendar from '@fullcalendar/react';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import { useQuery, useMutation } from '@tanstack/react-query';

interface CalendarEvent {
  id: number;
  title: string;
  start: string;
  channel: string;
  status: string;
  media_url?: string;
  content: string;
}

export const EditorialCalendar: React.FC = () => {
  const [selectedEvent, setSelectedEvent] = useState<CalendarEvent | null>(null);
  const [view, setView] = useState<'dayGridMonth' | 'timeGridWeek' | 'timeGridDay'>('timeGridWeek');

  const { data: events, refetch } = useQuery<CalendarEvent[]>({
    queryKey: ['calendar-events'],
    queryFn: async () => {
      const response = await fetch('/wp-json/fp-publisher/v1/calendar/events');
      return response.json();
    }
  });

  const updateEventMutation = useMutation({
    mutationFn: async ({ id, start, end }: { id: number; start: Date; end?: Date }) => {
      await fetch(`/wp-json/fp-publisher/v1/jobs/${id}/reschedule`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ scheduled_at: start.toISOString() })
      });
    },
    onSuccess: () => refetch()
  });

  const handleEventDrop = (info: any) => {
    updateEventMutation.mutate({
      id: parseInt(info.event.id),
      start: info.event.start!,
      end: info.event.end
    });
  };

  const handleEventClick = (info: any) => {
    const event = events?.find(e => e.id === parseInt(info.event.id));
    if (event) {
      setSelectedEvent(event);
    }
  };

  const handleDateClick = (info: any) => {
    // Apri composer con data pre-selezionata
    window.location.href = `/wp-admin/admin.php?page=fp-publisher-composer&date=${info.dateStr}`;
  };

  return (
    <div className="fp-calendar">
      <div className="calendar-header">
        <h1>📅 Calendario Editoriale</h1>
        <div className="view-switcher">
          <button onClick={() => setView('timeGridDay')}>Giorno</button>
          <button onClick={() => setView('timeGridWeek')} className="active">Settimana</button>
          <button onClick={() => setView('dayGridMonth')}>Mese</button>
        </div>
      </div>

      <FullCalendar
        plugins={[dayGridPlugin, timeGridPlugin, interactionPlugin]}
        initialView={view}
        events={events?.map(e => ({
          id: e.id.toString(),
          title: `${getChannelIcon(e.channel)} ${e.title}`,
          start: e.start,
          backgroundColor: getChannelColor(e.channel),
          borderColor: getChannelColor(e.channel)
        }))}
        editable={true}
        droppable={true}
        eventDrop={handleEventDrop}
        eventClick={handleEventClick}
        dateClick={handleDateClick}
        headerToolbar={{
          left: 'prev,next today',
          center: 'title',
          right: ''
        }}
        slotMinTime="06:00:00"
        slotMaxTime="24:00:00"
        height="auto"
      />

      {selectedEvent && (
        <EventDetailsPanel
          event={selectedEvent}
          onClose={() => setSelectedEvent(null)}
          onUpdate={refetch}
        />
      )}
    </div>
  );
};

function getChannelIcon(channel: string): string {
  const icons: Record<string, string> = {
    'meta_facebook': '📘',
    'meta_instagram': '📷',
    'youtube': '🎬',
    'tiktok': '🎵',
    'google_business': '📍',
    'wordpress_blog': '📝'
  };
  return icons[channel] || '📄';
}

function getChannelColor(channel: string): string {
  const colors: Record<string, string> = {
    'meta_facebook': '#1877F2',
    'meta_instagram': '#E4405F',
    'youtube': '#FF0000',
    'tiktok': '#000000',
    'google_business': '#4285F4',
    'wordpress_blog': '#21759B'
  };
  return colors[channel] || '#666';
}
```

---

## 4. 📚 Content Library

### UI Library

```
┌─────────────────────────────────────────────────────────────────────┐
│  📚 Content Library                              [+ Carica Nuovo]    │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  Cerca: [🔍 _____________]    Filtri: [Tipo ▼] [Tag ▼] [Data ▼]    │
│                                                                       │
│  CARTELLE:                                                           │
│  ┌───────────────────────────────────────────────────────────┐     │
│  │ 📁 Tutti (234)                                             │     │
│  │ 📁 Immagini (156)                                          │     │
│  │   ├─ Quadrate 1:1 (89)                                     │     │
│  │   ├─ Verticali 4:5 (45)                                    │     │
│  │   └─ Orizzontali 16:9 (22)                                 │     │
│  │ 📁 Video (67)                                              │     │
│  │   ├─ Reels/Shorts (34)                                     │     │
│  │   └─ Video lunghi (33)                                     │     │
│  │ 📁 Template Testo (11)                                     │     │
│  │ 📁 Brand Assets (15)                                       │     │
│  └───────────────────────────────────────────────────────────┘     │
│                                                                       │
│  GRID ASSETS:                                                        │
│  ┌──────────┬──────────┬──────────┬──────────┬──────────┐          │
│  │[Image]   │[Image]   │[Video]   │[Image]   │[Video]   │          │
│  │Tutorial  │Promo     │Demo      │Behind    │Review    │          │
│  │          │          │          │          │          │          │
│  │📷 1:1    │📷 4:5    │🎬 9:16   │📷 1:1    │🎬 16:9   │          │
│  │500KB     │1.2MB     │15MB      │800KB     │45MB      │          │
│  │          │          │45s       │          │5m 30s    │          │
│  │#tutorial │#promo    │#demo     │#bts      │#review   │          │
│  │          │          │          │          │          │          │
│  │[✓]       │[✓]       │[✓]       │[✓]       │[✓]       │          │
│  └──────────┴──────────┴──────────┴──────────┴──────────┘          │
│                                                                       │
│  AZIONI BULK:                                                        │
│  5 selezionati  [🏷️ Tag] [📁 Sposta] [🗑️ Elimina] [📤 Usa in Post] │
│                                                                       │
│  DETTAGLI ASSET SELEZIONATO:                                        │
│  ┌───────────────────────────────────────────────────────────┐     │
│  │  tutorial-video.mp4                                        │     │
│  │  ┌─────────────────┐                                       │     │
│  │  │ [Preview Player] │   📊 Metadata:                       │     │
│  │  │                  │   • Formato: 9:16 verticale          │     │
│  │  │                  │   • Dimensioni: 1080x1920            │     │
│  │  │                  │   • Durata: 45s                      │     │
│  │  │                  │   • Size: 15.3 MB                    │     │
│  │  └─────────────────┘   • Uploaded: 10/10/2025             │     │
│  │                                                            │     │
│  │  🏷️  Tag: tutorial, wordpress, howto                       │     │
│  │  📁 Cartella: Video > Reels/Shorts                         │     │
│  │  🎨 Canali compatibili: IG Reels, TikTok, YT Shorts       │     │
│  │  📈 Usato in: 3 post                                       │     │
│  │                                                            │     │
│  │  [✏️ Modifica] [📋 Duplica] [🗑️ Elimina] [📤 Usa in Post]  │     │
│  └───────────────────────────────────────────────────────────┘     │
│                                                                       │
└─────────────────────────────────────────────────────────────────────┘
```

### Implementazione Library

```typescript
// fp-digital-publisher/assets/admin/components/Library/ContentLibrary.tsx

import React, { useState } from 'react';
import { useQuery, useMutation } from '@tanstack/react-query';
import { AssetGrid } from './AssetGrid';
import { AssetUpload } from './AssetUpload';
import { AssetDetails } from './AssetDetails';
import { FolderTree } from './FolderTree';

interface Asset {
  id: number;
  filename: string;
  url: string;
  type: 'image' | 'video';
  mime_type: string;
  size: number;
  width?: number;
  height?: number;
  duration?: number;
  format_category: string;
  tags: string[];
  folder_id?: number;
  uploaded_at: string;
  used_in_posts: number;
}

export const ContentLibrary: React.FC = () => {
  const [selectedAssets, setSelectedAssets] = useState<number[]>([]);
  const [activeAsset, setActiveAsset] = useState<Asset | null>(null);
  const [filters, setFilters] = useState({
    type: 'all',
    folder: null,
    search: ''
  });

  const { data: assets, refetch } = useQuery<Asset[]>({
    queryKey: ['library-assets', filters],
    queryFn: async () => {
      const params = new URLSearchParams(filters as any);
      const response = await fetch(`/wp-json/fp-publisher/v1/library/assets?${params}`);
      return response.json();
    }
  });

  const uploadMutation = useMutation({
    mutationFn: async (files: File[]) => {
      const formData = new FormData();
      files.forEach(file => formData.append('files[]', file));
      
      const response = await fetch('/wp-json/fp-publisher/v1/library/upload', {
        method: 'POST',
        body: formData
      });
      return response.json();
    },
    onSuccess: () => refetch()
  });

  const bulkTagMutation = useMutation({
    mutationFn: async ({ assetIds, tags }: { assetIds: number[]; tags: string[] }) => {
      await fetch('/wp-json/fp-publisher/v1/library/bulk-tag', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ asset_ids: assetIds, tags })
      });
    },
    onSuccess: () => refetch()
  });

  const handleUseInPost = (asset: Asset) => {
    // Apri composer con asset pre-caricato
    const params = new URLSearchParams({ asset_id: asset.id.toString() });
    window.location.href = `/wp-admin/admin.php?page=fp-publisher-composer&${params}`;
  };

  return (
    <div className="fp-library">
      <div className="library-header">
        <h1>📚 Content Library</h1>
        <button onClick={() => setShowUpload(true)}>+ Carica Nuovo</button>
      </div>

      <div className="library-toolbar">
        <input
          type="search"
          placeholder="🔍 Cerca asset..."
          value={filters.search}
          onChange={(e) => setFilters({ ...filters, search: e.target.value })}
        />
        <select
          value={filters.type}
          onChange={(e) => setFilters({ ...filters, type: e.target.value })}
        >
          <option value="all">Tutti i tipi</option>
          <option value="image">Immagini</option>
          <option value="video">Video</option>
        </select>
      </div>

      <div className="library-main">
        <aside className="library-sidebar">
          <FolderTree
            onSelect={(folderId) => setFilters({ ...filters, folder: folderId })}
          />
        </aside>

        <main className="library-content">
          <AssetGrid
            assets={assets ?? []}
            selected={selectedAssets}
            onSelect={setSelectedAssets}
            onAssetClick={setActiveAsset}
          />

          {selectedAssets.length > 0 && (
            <div className="bulk-actions">
              <span>{selectedAssets.length} selezionati</span>
              <button onClick={() => /* Tag modal */ {}}>🏷️ Tag</button>
              <button onClick={() => /* Move modal */ {}}>📁 Sposta</button>
              <button onClick={() => /* Delete confirm */ {}}>🗑️ Elimina</button>
              <button onClick={() => handleUseInPost(assets![0])}>📤 Usa in Post</button>
            </div>
          )}
        </main>

        {activeAsset && (
          <aside className="library-details">
            <AssetDetails
              asset={activeAsset}
              onClose={() => setActiveAsset(null)}
              onUpdate={refetch}
              onUseInPost={handleUseInPost}
            />
          </aside>
        )}
      </div>
    </div>
  );
};
```

---

## 5. 📊 Analytics Dashboard

### UI Analytics

```
┌─────────────────────────────────────────────────────────────────────┐
│  📊 Analytics                  Periodo: [Ultimi 30 giorni ▼]         │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  METRICHE OVERVIEW:                                                  │
│  ┌─────────────┬─────────────┬─────────────┬─────────────┐         │
│  │ Reach Totale│ Engagement  │ Click       │ Conversioni │         │
│  │   156K      │    12.3K    │   3.4K      │     247     │         │
│  │  +23% ↗     │   +18% ↗    │  +31% ↗     │   +12% ↗    │         │
│  └─────────────┴─────────────┴─────────────┴─────────────┘         │
│                                                                       │
│  PERFORMANCE PER CANALE:                                             │
│  ┌───────────────────────────────────────────────────────────┐     │
│  │  Canale      │ Posts │ Reach  │ Eng. │ Eng. Rate │ Best   │     │
│  ├──────────────┼───────┼────────┼──────┼───────────┼────────┤     │
│  │ 📷 Instagram │  47   │ 89K    │ 8.9K │  10.0%    │ 18:00  │     │
│  │ 📘 Facebook  │  35   │ 45K    │ 2.1K │   4.7%    │ 12:00  │     │
│  │ 🎬 YouTube   │  12   │ 15K    │ 890  │   5.9%    │ 20:00  │     │
│  │ 🎵 TikTok    │   8   │  7K    │ 520  │   7.4%    │ 19:00  │     │
│  └──────────────┴───────┴────────┴──────┴───────────┴────────┘     │
│                                                                       │
│  ENGAGEMENT TREND (30 giorni):                                       │
│  ┌───────────────────────────────────────────────────────────┐     │
│  │  [Grafico area stacked: Likes, Comments, Shares, Saves]   │     │
│  │                                                            │     │
│  │    12K ┤                                          ╱──      │     │
│  │    10K ┤                                    ╱────          │     │
│  │     8K ┤                            ╱──────               │     │
│  │     6K ┤                    ╱──────                        │     │
│  │     4K ┤            ╱──────                                │     │
│  │     2K ┤    ╱──────                                        │     │
│  │      0 └────┴────┴────┴────┴────┴────┴────┴────┴────      │     │
│  │         1    5   10   15   20   25   30 (giorni)         │     │
│  └───────────────────────────────────────────────────────────┘     │
│                                                                       │
│  BEST PERFORMING CONTENT:                                            │
│  ┌───────────────────────────────────────────────────────────┐     │
│  │  Tipo              │ Post              │ Engagement        │     │
│  ├────────────────────┼───────────────────┼──────────────────┤     │
│  │ 🎬 Reel Verticale  │ "Tutorial WP..."  │ 2.3K (15% rate)  │     │
│  │ 📷 Carosello 1:1   │ "10 tips..."      │ 1.8K (12% rate)  │     │
│  │ 🎬 Short 9:16      │ "Quick demo..."   │ 1.5K (18% rate)  │     │
│  └────────────────────┴───────────────────┴──────────────────┘     │
│                                                                       │
│  AUDIENCE INSIGHTS:                                                  │
│  ┌──────────────────────────┬──────────────────────────────┐       │
│  │ Top Locations:            │ Top Demographics:            │       │
│  │ 1. 🇮🇹 Italia      45%    │ • 25-34 anni        38%      │       │
│  │ 2. 🇺🇸 USA         22%    │ • 35-44 anni        31%      │       │
│  │ 3. 🇬🇧 UK          18%    │ • 18-24 anni        18%      │       │
│  │ 4. 🇩🇪 Germania    10%    │ • 45-54 anni        10%      │       │
│  │ 5. 🇫🇷 Francia      5%    │ • Maschi            52%      │       │
│  │                           │ • Femmine           48%      │       │
│  └──────────────────────────┴──────────────────────────────┘       │
│                                                                       │
│  [📥 Esporta Report PDF]  [📊 Esporta Excel]  [📧 Invia via Email]  │
│                                                                       │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 6. 👥 Team & Collaboration

### Features Team

1. **Gestione Ruoli**:
   - Admin: Accesso completo
   - Editor: Crea, modifica, pubblica
   - Contributor: Crea bozze, non pubblica
   - Viewer: Solo visualizzazione analytics

2. **Approval Workflow**:
   - Bozza → Revisione → Approvato → Scheduled
   - Commenti interni sui post
   - Notifiche email

3. **Activity Log**:
   - Chi ha fatto cosa quando
   - Audit trail completo

---

## 7. 🤖 Automazioni e AI

### Funzionalità Smart

1. **Best Time to Post**:
   - Analisi engagement storico
   - Suggerimento orari ottimali per canale

2. **Content Suggestions**:
   - Hashtag trending
   - Caption suggestions basate su AI
   - Emoji recommendations

3. **Auto-Reposting**:
   - Ripubblica top posts automaticamente
   - Evergreen content rotation

4. **Smart Queues**:
   - Fill vuoti calendario automaticamente
   - Bilanciamento frequenza post per canale

---

## 8. 📱 Streams Monitor (Real-time)

```
┌─────────────────────────────────────────────────────────────────────┐
│  📱 Streams Monitor                                    [⟳ Aggiorna]  │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  Column: [📷 Instagram] [📘 Facebook] [🎬 YouTube] [🎵 TikTok]       │
│                                                                       │
│  ┌─────────────────┬─────────────────┬─────────────────┐           │
│  │ 📷 Instagram    │ 📘 Facebook     │ 🎬 YouTube      │           │
│  ├─────────────────┼─────────────────┼─────────────────┤           │
│  │ @user123:       │ 👤 Mario Rossi: │ 💬 Nuovo        │           │
│  │ "Ottimo! 😍"    │ "Grazie!"       │ commento:       │           │
│  │ 2 min fa        │ 5 min fa        │ "Utile!"        │           │
│  │ [♥️ Rispondi]    │ [↩️ Rispondi]    │ 8 min fa        │           │
│  │                 │                 │ [↩️ Rispondi]    │           │
│  │ @user456:       │ 👤 Luca Bianchi │                 │           │
│  │ "Domanda..."    │ Ha reagito ❤️   │ 📹 Nuova view:  │           │
│  │ 15 min fa       │ 12 min fa       │ +1K views       │           │
│  │ [↩️ Rispondi]    │                 │ 20 min fa       │           │
│  │                 │                 │                 │           │
│  │ 🔔 Notifica:    │ 📊 Stats:       │ 📊 Stats:       │           │
│  │ Nuovo follower  │ +23 reactions   │ Avg. watch      │           │
│  │ 30 min fa       │ oggi            │ time: 2m 15s    │           │
│  └─────────────────┴─────────────────┴─────────────────┘           │
│                                                                       │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 9. 🔧 Backend Architecture

### Nuovi Endpoint API

```php
// Dashboard
GET  /wp-json/fp-publisher/v1/dashboard/stats
GET  /wp-json/fp-publisher/v1/dashboard/top-posts
GET  /wp-json/fp-publisher/v1/dashboard/upcoming

// Composer
POST /wp-json/fp-publisher/v1/publish/multi-channel
POST /wp-json/fp-publisher/v1/compose/detect-format
GET  /wp-json/fp-publisher/v1/compose/suggest-channels
POST /wp-json/fp-publisher/v1/compose/preview

// Calendar
GET  /wp-json/fp-publisher/v1/calendar/events
POST /wp-json/fp-publisher/v1/jobs/{id}/reschedule
POST /wp-json/fp-publisher/v1/jobs/{id}/duplicate

// Library
GET  /wp-json/fp-publisher/v1/library/assets
POST /wp-json/fp-publisher/v1/library/upload
POST /wp-json/fp-publisher/v1/library/bulk-tag
DELETE /wp-json/fp-publisher/v1/library/assets/{id}

// Analytics
GET  /wp-json/fp-publisher/v1/analytics/overview
GET  /wp-json/fp-publisher/v1/analytics/engagement
GET  /wp-json/fp-publisher/v1/analytics/top-posts
GET  /wp-json/fp-publisher/v1/analytics/export

// Team
GET  /wp-json/fp-publisher/v1/team/members
POST /wp-json/fp-publisher/v1/team/invite
GET  /wp-json/fp-publisher/v1/team/activity-log

// Streams (WebSocket o Long-polling)
GET  /wp-json/fp-publisher/v1/streams/feed
POST /wp-json/fp-publisher/v1/streams/respond
```

---

## 10. 📦 Stack Tecnologico

### Frontend
- **React 18** + **TypeScript**
- **TanStack Query** (React Query) per data fetching
- **FullCalendar** per calendario
- **Recharts** per grafici analytics
- **TailwindCSS** per styling
- **Zustand** per state management

### Backend (già presente)
- **PHP 8.1+**
- **WordPress REST API**
- **Queue System** (già implementato)
- **OAuth 2.0** clients (già implementati)

### Build & Deploy
- **esbuild** (già configurato)
- **Composer** (già configurato)
- **npm** scripts

---

## 📋 Piano di Implementazione Completo

### Fase 1: Foundation (2 settimane)
1. ✅ Setup React workspace con TypeScript
2. ✅ Configurare routing SPA interno
3. ✅ Creare componenti UI base (Card, Button, Input, etc.)
4. ✅ Implementare API endpoints base
5. ✅ Setup TanStack Query

### Fase 2: Dashboard & Composer (3 settimane)
1. ✅ Dashboard overview con stats
2. ✅ Composer universale
3. ✅ Format detection
4. ✅ Channel selector smart
5. ✅ Preview mode

### Fase 3: Calendario & Library (2 settimane)
1. ✅ Calendario editoriale con FullCalendar
2. ✅ Drag & drop eventi
3. ✅ Content library con upload
4. ✅ Asset management

### Fase 4: Analytics (2 settimane)
1. ✅ Aggregazione dati da canali
2. ✅ Dashboard analytics
3. ✅ Grafici e charts
4. ✅ Export reports

### Fase 5: Team & Advanced (2 settimane)
1. ✅ Gestione team e ruoli
2. ✅ Approval workflow
3. ✅ Activity log
4. ✅ Notifiche

### Fase 6: Polish & Launch (1 settimana)
1. ✅ Testing completo
2. ✅ Performance optimization
3. ✅ Documentazione
4. ✅ Launch

**Totale: 12 settimane (3 mesi)**

---

## 💰 Pricing Strategy

### Free Version
- 1 cliente
- 2 canali social
- 10 post/mese scheduled
- Analytics base

### Pro Version (€29/mese)
- 5 clienti
- Tutti i 6 canali
- Post illimitati
- Analytics completo
- Content library 5GB

### Agency Version (€99/mese)
- Clienti illimitati
- Team collaboration
- White label
- Content library 50GB
- Priority support

### Enterprise (Custom)
- On-premise deployment
- Custom integrations
- SLA garantito
- Dedicated account manager

---

## 🎯 Vantaggi Competitivi

### vs Hootsuite
✅ **Integrato in WordPress** (no piattaforma esterna)
✅ **Una tantum o abbonamento flessibile** (Hootsuite solo SaaS)
✅ **Pubblicazione WordPress nativa**
✅ **Self-hosted** (privacy e controllo dati)

### vs Buffer
✅ **Analytics più avanzato**
✅ **Content library integrata**
✅ **Smart format detection**
✅ **Team collaboration incluso**

### vs Later
✅ **Più canali supportati** (6 vs 4)
✅ **Queue system enterprise-grade**
✅ **WordPress come CMS centrale**

---

## ✅ Conclusione

Questa architettura trasforma **FP Digital Publisher** in un **Hootsuite completo** dentro WordPress, con:

1. ✅ **Dashboard unificata** per overview rapido
2. ✅ **Composer universale** con smart suggestions
3. ✅ **Calendario editoriale** drag & drop
4. ✅ **Content library** organizzata
5. ✅ **Analytics cross-platform**
6. ✅ **Team collaboration**
7. ✅ **Automazioni AI-powered**

**Unico nel mercato WordPress!** 🚀

Vuoi che inizi l'implementazione di una componente specifica?
