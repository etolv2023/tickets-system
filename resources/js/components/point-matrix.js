/**
 * Inline saving for the points matrix (F18).
 *
 * Every cell posts on change. The numbers here become money, so a failed save
 * must be loud rather than a silently stale screen.
 */
export default function pointMatrix() {
    return {
        error: '',
        saved: '',

        save(ticketType, scope, side, points, isActive) {
            this.error = '';

            return fetch('/admin/point-rules', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    ticket_type: ticketType,
                    scope,
                    side,
                    points,
                    is_active: isActive,
                }),
            })
                .then((r) => (r.ok ? r.json() : Promise.reject(r)))
                .then((data) => {
                    this.saved = data.note;
                    setTimeout(() => (this.saved = ''), 4000);
                })
                .catch(() => {
                    this.error = 'مقدرناش نحفظ التعديل. حدّث الصفحة وجرّب تاني.';
                });
        },
    };
}
