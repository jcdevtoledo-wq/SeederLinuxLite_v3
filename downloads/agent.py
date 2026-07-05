#!/usr/bin/env python3
"""
SeederLinux Lite - Provisioning Agent
=====================================

A lightweight agent that downloads and executes provisioning bundles
from the SeederLinux Lite server.

Usage:
    python3 agent.py --org ORG_ACRONYM [--server URL] [--dry-run]

Example:
    python3 agent.py --org COMARA --server http://192.168.1.100

Author: SeederLinux Team
License: MIT
Version: 1.0.0
"""

import argparse
import os
import sys
import platform
import subprocess
import tempfile
import hashlib
import json
from datetime import datetime
from urllib.request import urlopen, Request
from urllib.error import URLError, HTTPError
from urllib.parse import urljoin

# Configuration defaults
DEFAULT_SERVER_URL = "http://localhost"
AGENT_VERSION = "1.0.0"
TIMEOUT_SECONDS = 30
MAX_RETRIES = 3

class Colors:
    """ANSI color codes for terminal output."""
    RED = '\033[0;31m'
    GREEN = '\033[0;32m'
    YELLOW = '\033[1;33m'
    BLUE = '\033[0;34m'
    CYAN = '\033[0;36m'
    BOLD = '\033[1m'
    RESET = '\033[0m'

class Logger:
    """Simple logger with colored output."""

    @staticmethod
    def info(message):
        print(f"{Colors.BLUE}[INFO]{Colors.RESET} {message}")

    @staticmethod
    def success(message):
        print(f"{Colors.GREEN}[OK]{Colors.RESET} {message}")

    @staticmethod
    def warning(message):
        print(f"{Colors.YELLOW}[WARN]{Colors.RESET} {message}")

    @staticmethod
    def error(message):
        print(f"{Colors.RED}[ERROR]{Colors.RESET} {message}")

    @staticmethod
    def header(message):
        print(f"\n{Colors.CYAN}{Colors.BOLD}{'='*60}{Colors.RESET}")
        print(f"{Colors.CYAN}{Colors.BOLD}{message}{Colors.RESET}")
        print(f"{Colors.CYAN}{Colors.BOLD}{'='*60}{Colors.RESET}\n")

class SeederAgent:
    """Main agent class for provisioning."""

    def __init__(self, server_url, org_acronym, dry_run=False):
        self.server_url = server_url.rstrip('/')
        self.org_acronym = org_acronym.upper()
        self.dry_run = dry_run
        self.bundle_dir = tempfile.gettempdir()

    def run(self):
        """Execute the provisioning process."""
        Logger.header("SEEDEALINUX LITE - PROVISIONING AGENT")

        # Print system info
        self._print_system_info()

        # Check prerequisites
        if not self._check_prerequisites():
            return False

        # Download bundle
        Logger.info(f"Baixando bundle para organização: {self.org_acronym}")
        bundle_path = self._download_bundle()

        if not bundle_path:
            Logger.error("Falha ao baixar bundle de provisionamento")
            return False

        Logger.success(f"Bundle baixado: {bundle_path}")

        # Show bundle info
        self._show_bundle_info(bundle_path)

        if self.dry_run:
            Logger.warning("Modo dry-run: bundle não será executado")
            Logger.info(f"Bundle salvo em: {bundle_path}")
            return True

        # Execute bundle
        Logger.info("Iniciando execução do bundle...")
        success = self._execute_bundle(bundle_path)

        # Cleanup
        try:
            os.remove(bundle_path)
            Logger.info("Arquivo temporário removido")
        except Exception:
            pass

        return success

    def _print_system_info(self):
        """Print system information."""
        Logger.info(f"Agente versão: {AGENT_VERSION}")
        Logger.info(f"Servidor: {self.server_url}")
        Logger.info(f"Organização: {self.org_acronym}")
        Logger.info(f"Sistema: {platform.system()} {platform.release()}")
        Logger.info(f"Host: {platform.node()}")
        Logger.info(f"Python: {platform.python_version()}")
        print()

    def _check_prerequisites(self):
        """Check if system meets requirements."""
        checks_passed = True

        # Check if running as root or with sudo
        if os.geteuid() != 0:
            Logger.error("Este agente deve ser executado como root (sudo)")
            Logger.info("Use: sudo python3 agent.py --org COMARA")
            checks_passed = False

        # Check network connectivity
        try:
            Logger.info("Verificando conectividade com servidor...")
            request = Request(
                f"{self.server_url}/api/?action=stats",
                headers={'User-Agent': f'SeederAgent/{AGENT_VERSION}'}
            )
            response = urlopen(request, timeout=TIMEOUT_SECONDS)
            data = json.loads(response.read().decode())

            if data.get('success'):
                Logger.success("Conexão com servidor OK")
                Logger.info(f"Organizações no servidor: {data['data'].get('organizations', 'N/A')}")
            else:
                Logger.warning("Servidor respondeu com erro")
        except URLError as e:
            Logger.error(f"Não foi possível conectar ao servidor: {e}")
            Logger.info("Verifique se o servidor está acessível e a URL está correta")
            checks_passed = False
        except Exception as e:
            Logger.warning(f"Erro ao verificar servidor: {e}")

        # Check required commands
        required_commands = ['bash']
        for cmd in required_commands:
            if not self._command_exists(cmd):
                Logger.error(f"Comando obrigatório não encontrado: {cmd}")
                checks_passed = False

        return checks_passed

    def _command_exists(self, command):
        """Check if a command exists in PATH."""
        try:
            subprocess.run(
                ['which', command],
                capture_output=True,
                check=True
            )
            return True
        except subprocess.CalledProcessError:
            return False

    def _download_bundle(self):
        """Download the provisioning bundle."""
        bundle_url = f"{self.server_url}/api/?action=bundle-download&id={self.org_acronym}"
        bundle_filename = f"provision-{self.org_acronym.lower()}-{datetime.now().strftime('%Y%m%d%H%M%S')}.sh"
        bundle_path = os.path.join(self.bundle_dir, bundle_filename)

        retries = 0
        while retries < MAX_RETRIES:
            try:
                request = Request(
                    bundle_url,
                    headers={'User-Agent': f'SeederAgent/{AGENT_VERSION}'}
                )

                with urlopen(request, timeout=TIMEOUT_SECONDS) as response:
                    content = response.read()

                    # Save bundle
                    with open(bundle_path, 'wb') as f:
                        f.write(content)

                    # Verify file
                    if os.path.getsize(bundle_path) > 0:
                        os.chmod(bundle_path, 0o755)
                        return bundle_path
                    else:
                        Logger.error("Bundle vazio recebido")
                        return None

            except HTTPError as e:
                if e.code == 404:
                    Logger.error(f"Organização '{self.org_acronym}' não encontrada no servidor")
                else:
                    Logger.error(f"Erro HTTP: {e.code} - {e.reason}")
                return None

            except URLError as e:
                retries += 1
                if retries < MAX_RETRIES:
                    Logger.warning(f"Tentativa {retries}/{MAX_RETRIES} falhou. Tentando novamente...")
                    import time
                    time.sleep(2)
                    continue
                Logger.error(f"Erro de conexão: {e}")
                return None

            except Exception as e:
                Logger.error(f"Erro inesperado: {e}")
                return None

        return None

    def _show_bundle_info(self, bundle_path):
        """Display bundle information."""
        file_size = os.path.getsize(bundle_path)
        Logger.info(f"Tamanho: {file_size / 1024:.1f} KB")

        # Count scripts in bundle
        try:
            with open(bundle_path, 'r') as f:
                content = f.read()
                script_count = content.count('# ===== SCRIPT')
                Logger.info(f"Scripts incluídos: {script_count}")
        except Exception:
            pass

    def _execute_bundle(self, bundle_path):
        """Execute the provisioning bundle."""
        Logger.header("EXECUTANDO BUNDLE DE PROVISIONAMENTO")

        try:
            # Execute with bash
            process = subprocess.Popen(
                ['bash', bundle_path],
                stdout=subprocess.PIPE,
                stderr=subprocess.STDOUT,
                universal_newlines=True
            )

            # Stream output in real-time
            for line in process.stdout:
                print(line, end='')

            process.wait()

            if process.returncode == 0:
                Logger.header("PROVISIONAMENTO CONCLUÍDO COM SUCESSO")
                Logger.info("Reinicie a estação para aplicar todas as alterações.")
                return True
            else:
                Logger.error(f"Bundle falhou com código de saída: {process.returncode}")
                return False

        except Exception as e:
            Logger.error(f"Erro ao executar bundle: {e}")
            return False


def main():
    """Main entry point."""
    parser = argparse.ArgumentParser(
        description='SeederLinux Lite - Agente de Provisionamento',
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Exemplos:
  sudo python3 agent.py --org COMARA
  sudo python3 agent.py --org COMARA --server http://192.168.1.100
  python3 agent.py --org COMARA --dry-run
        """
    )

    parser.add_argument(
        '--org', '-o',
        required=True,
        help='Sigla da organização (ex: COMARA)'
    )

    parser.add_argument(
        '--server', '-s',
        default=DEFAULT_SERVER_URL,
        help=f'URL do servidor SeederLinux (padrão: {DEFAULT_SERVER_URL})'
    )

    parser.add_argument(
        '--dry-run',
        action='store_true',
        help='Baixar bundle sem executar'
    )

    parser.add_argument(
        '--version', '-v',
        action='version',
        version=f'SeederLinux Agent {AGENT_VERSION}'
    )

    args = parser.parse_args()

    # Create and run agent
    agent = SeederAgent(
        server_url=args.server,
        org_acronym=args.org,
        dry_run=args.dry_run
    )

    success = agent.run()
    sys.exit(0 if success else 1)


if __name__ == '__main__':
    main()
