            </div> <!-- .content-body -->
        </main> <!-- .main-content -->
    </div> <!-- .app-container -->

    <!-- Toast Notifications Container -->
    <div id="toast-container" class="toast-container"></div>

    <!-- Modal: testar canal -->
    <div id="channel-player-modal" class="channel-player-modal" aria-hidden="true">
        <div class="channel-player-backdrop" onclick="closeChannelPlayer()"></div>
        <div class="channel-player-dialog">
            <div class="channel-player-header">
                <div>
                    <h3 id="channel-player-title">Testar Canal</h3>
                    <p id="channel-player-status">Aguardando...</p>
                </div>
                <button type="button" class="channel-player-close" onclick="closeChannelPlayer()" title="Fechar">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="channel-player-video-wrap">
                <iframe id="channel-player-frame" class="channel-player-video" allow="autoplay; fullscreen" allowfullscreen title="Player do canal"></iframe>
            </div>
            <p id="channel-player-url" class="channel-player-url"></p>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
</body>
</html>
