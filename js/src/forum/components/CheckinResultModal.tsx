import app from 'flarum/forum/app';
import Modal, { type IInternalModalAttrs } from 'flarum/common/components/Modal';
import checkinMessages from '../utils/checkinMessages';

interface CheckinResultModalAttrs extends IInternalModalAttrs {
  success: boolean;
}

export default class CheckinResultModal extends Modal<CheckinResultModalAttrs> {
  className() {
    return 'checkInResultModal Modal--small';
  }

  title() {
    return app.translator.trans(`primexcalibur-checkin-with-history.forum.check-in-${this.attrs.success ? 'success' : 'failed'}`);
  }

  content() {
    return (
      <div className="Modal-body">
        {this.attrs.success ? (
          checkinMessages().map((message) => <p className="successText">{message}</p>)
        ) : (
          <p>{app.translator.trans('primexcalibur-checkin-with-history.forum.try-again-later')}</p>
        )}
      </div>
    );
  }
}
