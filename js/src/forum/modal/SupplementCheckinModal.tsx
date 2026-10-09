import app from 'flarum/forum/app';
import Modal, { type IInternalModalAttrs } from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import type Mithril from 'mithril';
import type { ApiPayloadSingle } from 'flarum/common/Store';

export interface SupplementCheckinModalAttrs extends IInternalModalAttrs {
  info: {
    dateStr: string;
    [key: string]: any;
  };
  callback?: () => void;
}

export default class SupplementCheckinModal extends Modal<SupplementCheckinModalAttrs> {
  loading: boolean = false;

  title(): Mithril.Children {
    return app.translator.trans('mattoid-daily-check-in-history.forum.modal.checkin');
  }

  className(): string {
    return 'SupplementCheckinModal Modal--small';
  }

  content(): Mithril.Children {
    return (
      <div className="Modal-body" style={{ textAlign: 'center' }}>
        <div className="Form-group">
          <label className="label">
            {app.translator.trans('mattoid-daily-check-in-history.forum.modal.supplement-checkin-desc', {
              dateStr: this.attrs.info.dateStr,
            })}
          </label>
        </div>
        <div className="Form-group">
          <Button type="submit" className="Button Button--primary" loading={this.loading}>
            {app.translator.trans('mattoid-daily-check-in-history.forum.modal.submit')}
          </Button>
        </div>
      </div>
    );
  }

  async onsubmit(e: Event): Promise<void> {
    e.preventDefault();
    this.loading = true;

    try {
      const payload = await app.request<ApiPayloadSingle>({
        method: 'POST',
        url: app.forum.attribute('apiUrl') + '/supplement/checkin',
        body: {
          date: this.attrs.info.dateStr,
        },
      });

      payload.included?.forEach((user) => app.store.pushObject(user));

      if (this.attrs.callback) {
        this.attrs.callback();
      }
      this.hide();
    } catch (err) {
      this.loading = false;
      m.redraw();
    }
  }
}
