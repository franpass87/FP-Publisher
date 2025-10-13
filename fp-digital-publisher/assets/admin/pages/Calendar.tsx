import { createElement, useState, useEffect } from '@wordpress/element';
import { useClient } from '../hooks/useClient';

interface CalendarEvent {
  id: number;
  title: string;
  channel: string;
  date: string;
  time: string;
  status: string;
}

export const Calendar: React.FC = () => {
  const { selectedClientId } = useClient();
  const [currentDate, setCurrentDate] = useState(new Date());
  const [events, setEvents] = useState<CalendarEvent[]>([]);
  const [view, setView] = useState<'month' | 'week'>('month');

  useEffect(() => {
    if (selectedClientId) {
      fetchEvents();
    }
  }, [selectedClientId, currentDate]);

  const fetchEvents = async () => {
    if (!selectedClientId) return;

    try {
      const params = new URLSearchParams({ client_id: selectedClientId.toString() });
      const response = await fetch(`/wp-json/fp-publisher/v1/jobs?${params}`);
      const data = await response.json();

      const calendarEvents: CalendarEvent[] = (data.jobs || []).map((job: any) => ({
        id: job.id,
        title: job.payload?.message?.substring(0, 50) || 'Post',
        channel: job.channel,
        date: job.run_at.split('T')[0],
        time: job.run_at.split('T')[1]?.substring(0, 5) || '00:00',
        status: job.status,
      }));

      setEvents(calendarEvents);
    } catch (error) {
      console.error('Failed to fetch events:', error);
    }
  };

  const getDaysInMonth = () => {
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    return new Date(year, month + 1, 0).getDate();
  };

  const getFirstDayOfMonth = () => {
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    return new Date(year, month, 1).getDay();
  };

  const getEventsForDay = (day: number) => {
    const dateStr = `${currentDate.getFullYear()}-${String(currentDate.getMonth() + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    return events.filter(e => e.date === dateStr);
  };

  const previousMonth = () => {
    setCurrentDate(new Date(currentDate.getFullYear(), currentDate.getMonth() - 1));
  };

  const nextMonth = () => {
    setCurrentDate(new Date(currentDate.getFullYear(), currentDate.getMonth() + 1));
  };

  const today = () => {
    setCurrentDate(new Date());
  };

  const renderCalendar = () => {
    const daysInMonth = getDaysInMonth();
    const firstDay = getFirstDayOfMonth();
    const days = [];

    // Empty cells for days before month starts
    for (let i = 0; i < firstDay; i++) {
      days.push(<div key={`empty-${i}`} className="calendar-day empty" />);
    }

    // Days of the month
    for (let day = 1; day <= daysInMonth; day++) {
      const dayEvents = getEventsForDay(day);
      const isToday = new Date().toDateString() === new Date(currentDate.getFullYear(), currentDate.getMonth(), day).toDateString();

      days.push(
        <div key={day} className={`calendar-day ${isToday ? 'today' : ''}`}>
          <div className="day-number">{day}</div>
          <div className="day-events">
            {dayEvents.slice(0, 3).map(event => (
              <div key={event.id} className={`event event-${event.status}`}>
                <span className="event-time">{event.time}</span>
                <span className="event-title">{event.title}</span>
              </div>
            ))}
            {dayEvents.length > 3 && (
              <div className="event-more">+{dayEvents.length - 3} altri</div>
            )}
          </div>
        </div>
      );
    }

    return days;
  };

  return (
    <div className="fp-calendar">
      <div className="calendar-header">
        <h1>📅 Calendario Pubblicazioni</h1>
        <div className="calendar-controls">
          <button className="button" onClick={previousMonth}>← Precedente</button>
          <button className="button" onClick={today}>Oggi</button>
          <button className="button" onClick={nextMonth}>Successivo →</button>
        </div>
      </div>

      <div className="calendar-month-title">
        {currentDate.toLocaleDateString('it-IT', { month: 'long', year: 'numeric' })}
      </div>

      <div className="calendar-grid">
        <div className="calendar-weekdays">
          {['Dom', 'Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab'].map(day => (
            <div key={day} className="weekday">{day}</div>
          ))}
        </div>
        <div className="calendar-days">
          {renderCalendar()}
        </div>
      </div>

      <div className="calendar-legend">
        <div className="legend-item">
          <span className="legend-dot pending"></span>
          <span>Schedulato</span>
        </div>
        <div className="legend-item">
          <span className="legend-dot completed"></span>
          <span>Pubblicato</span>
        </div>
        <div className="legend-item">
          <span className="legend-dot failed"></span>
          <span>Fallito</span>
        </div>
      </div>
    </div>
  );
};
