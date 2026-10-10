import app from 'flarum/forum/app';
import type { ApiPayloadSingle } from 'flarum/common/Store';

export async function submitCheckIn(date?: string): Promise<void> {
  const payload = await app.request<ApiPayloadSingle>({
    method: 'POST',
    url: `${app.forum.attribute('apiUrl')}/${date ? 'supplement/checkin' : 'checkin'}`,
    body: date ? { date } : undefined,
  });

  // Both API responses include the updated user; Flarum updates every view of that model.
  app.store.pushPayload(payload);
  m.redraw();
}

export function forumTime(): Date {
  return new Date(Date.now() + Number(app.forum.attribute('forumCheckinTimeZone') || 0) * 3600000);
}
