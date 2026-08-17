import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/providers.dart';

/// The three screens a first-time install opens with.
///
/// Shown once per installation and never again — the flag is written the
/// moment the last page is dismissed, including when it is skipped,
/// because someone who skipped an introduction has expressed a clear
/// opinion about seeing it a second time.
class IntroScreen extends ConsumerStatefulWidget {
  const IntroScreen({super.key});

  @override
  ConsumerState<IntroScreen> createState() => _IntroScreenState();
}

class _IntroPage {
  const _IntroPage({
    required this.icon,
    required this.title,
    required this.body,
  });

  final IconData icon;
  final String title;
  final String body;
}

const _pages = <_IntroPage>[
  _IntroPage(
    icon: Icons.point_of_sale_outlined,
    title: 'Sell, even when the internet is not there',
    body: 'Take payments, print receipts and track stock on this computer. '
        'Sales are saved locally and sent to your account the moment you are '
        'back online, so a bad connection never stops a queue.',
  ),
  _IntroPage(
    icon: Icons.inventory_2_outlined,
    title: 'One set of books, everywhere',
    body: 'Products, prices and stock levels stay the same on this till, in '
        'your other branches and on the web dashboard. Change a price once '
        'and it is right everywhere.',
  ),
  _IntroPage(
    icon: Icons.insights_outlined,
    title: 'Know how the business is doing',
    body: 'Daily takings, best sellers and what is running low — ready when '
        'you open the app, not at the end of the month.',
  ),
];

class _IntroScreenState extends ConsumerState<IntroScreen> {
  final _controller = PageController();
  int _page = 0;

  bool get _isLast => _page == _pages.length - 1;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _finish() async {
    await ref.read(authRepositoryProvider).completeIntro();

    if (mounted) {
      context.go('/auth');
    }
  }

  void _next() {
    if (_isLast) {
      _finish();

      return;
    }

    _controller.nextPage(
      duration: const Duration(milliseconds: 280),
      curve: Curves.easeOutCubic,
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      body: SafeArea(
        child: Column(
          children: [
            Align(
              alignment: Alignment.topRight,
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: TextButton(
                  onPressed: _finish,
                  child: const Text('Skip'),
                ),
              ),
            ),
            Expanded(
              child: PageView.builder(
                controller: _controller,
                itemCount: _pages.length,
                onPageChanged: (index) => setState(() => _page = index),
                itemBuilder: (context, index) {
                  final page = _pages[index];

                  return Center(
                    child: ConstrainedBox(
                      constraints: const BoxConstraints(maxWidth: 560),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 32),
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Container(
                              padding: const EdgeInsets.all(28),
                              decoration: BoxDecoration(
                                color: theme.colorScheme.primaryContainer,
                                shape: BoxShape.circle,
                              ),
                              child: Icon(
                                page.icon,
                                size: 56,
                                color: theme.colorScheme.onPrimaryContainer,
                              ),
                            ),
                            const SizedBox(height: 32),
                            Text(
                              page.title,
                              textAlign: TextAlign.center,
                              style: theme.textTheme.headlineSmall,
                            ),
                            const SizedBox(height: 12),
                            Text(
                              page.body,
                              textAlign: TextAlign.center,
                              style: theme.textTheme.bodyLarge?.copyWith(
                                color: theme.colorScheme.onSurfaceVariant,
                                height: 1.5,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  );
                },
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(32, 0, 32, 32),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  for (var i = 0; i < _pages.length; i++)
                    AnimatedContainer(
                      duration: const Duration(milliseconds: 220),
                      margin: const EdgeInsets.symmetric(horizontal: 4),
                      height: 8,
                      width: i == _page ? 24 : 8,
                      decoration: BoxDecoration(
                        color: i == _page
                            ? theme.colorScheme.primary
                            : theme.colorScheme.outlineVariant,
                        borderRadius: BorderRadius.circular(4),
                      ),
                    ),
                  const Spacer(),
                  FilledButton.icon(
                    onPressed: _next,
                    icon: Icon(_isLast ? Icons.check : Icons.arrow_forward),
                    label: Text(_isLast ? 'Get started' : 'Next'),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
