import app from 'flarum/forum/app';
import Modal, { type IInternalModalAttrs } from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import type Mithril from 'mithril';
import { submitCheckIn } from '../utils/checkin';

export interface SupplementCheckinModalAttrs extends IInternalModalAttrs {
  date: string;
  callback?: () => void;
}

export default class SupplementCheckinModal extends Modal<SupplementCheckinModalAttrs> {
  loading: boolean = false;

  title(): Mithril.Children {
    return app.translator.trans('primexcalibur-checkin-with-history.forum.modal.checkin');
  }

  className(): string {
    return 'SupplementCheckinModal Modal--small';
  }

  content(): Mithril.Children {
    return (
      <div className="Modal-body" style={{ textAlign: 'center' }}>
        <div className="Form-group">
          <label className="label">
            {app.translator.trans('primexcalibur-checkin-with-history.forum.modal.supplement-checkin-desc', {
              dateStr: this.attrs.date,
            })}
          </label>
        </div>
        <div className="Form-group">
          <Button type="submit" className="Button Button--primary" loading={this.loading}>
            {app.translator.trans('primexcalibur-checkin-with-history.forum.modal.submit')}
          </Button>
        </div>
      </div>
    );
  }

  async onsubmit(e: Event): Promise<void> {
    e.preventDefault();
    this.loading = true;

    try {
      await submitCheckIn(this.attrs.date);

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
